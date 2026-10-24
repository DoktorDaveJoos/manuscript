<?php

declare(strict_types=1);

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Native\Desktop\Builder\Builder;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards the release bundles against build-time secrets.
 *
 * `native:publish` runs the prebuild commands with the notarization and
 * release secrets (NATIVEPHP_APPLE_ID_PASS, SENTRY_AUTH_TOKEN, GITHUB_TOKEN)
 * in its environment, then copies the app into the bundle. A config cached
 * then bakes those values into bootstrap/cache/config.php. Up to v0.10.2 the
 * file only stayed out because the vendor prune's `composer install` deletes
 * it (ComposerScripts::clearCompiled). Nothing caches config at build time
 * any more, the copy skips a cached config, and publish.yml fails the build
 * if a secret still reaches either packaged app.
 */
const BUNDLE_SECRETS_STEP = 'Verify no build secrets were bundled';

const FAKE_BUILD_SECRETS = [
    'NATIVEPHP_APPLE_ID_PASS' => 'abcd-efgh-ijkl-mnop',
    'SENTRY_AUTH_TOKEN' => 'sntrys_fake-sentry-auth-token',
    'GITHUB_TOKEN' => 'ghs_fakeGitHubActionsToken0123456789',
];

/**
 * @return array<string, mixed>|null
 */
function bundleSecretsStep(): ?array
{
    $steps = Yaml::parseFile(base_path('.github/workflows/publish.yml'))['jobs']['build-macos']['steps'];

    return collect($steps)->firstWhere('name', BUNDLE_SECRETS_STEP);
}

/**
 * Lays out both packaged macOS apps the way electron-builder leaves them.
 */
function fakePackagedMacApps(string $root): void
{
    foreach (['mac-arm64', 'mac'] as $arch) {
        $app = "{$root}/nativephp/electron/dist/{$arch}/Manuscript.app/Contents/Resources/build/app";

        File::ensureDirectoryExists("{$app}/bootstrap/cache");
        File::put("{$app}/bootstrap/cache/packages.php", '<?php return [];');
        File::put("{$app}/.env", "APP_ENV=production\n");
    }
}

/**
 * @param  array<string, string>  $secrets
 */
function runBundleSecretsStep(string $root, array $secrets = FAKE_BUILD_SECRETS): ProcessResult
{
    // GitHub Actions runs `shell: bash` steps as `bash --noprofile --norc -eo pipefail`.
    return Process::path($root)
        ->env($secrets)
        ->run(['bash', '--noprofile', '--norc', '-eo', 'pipefail', '-c', bundleSecretsStep()['run']]);
}

beforeEach(function (): void {
    $this->bundleRoot = sys_get_temp_dir().'/manuscript-bundle-'.uniqid();
    File::ensureDirectoryExists($this->bundleRoot);
});

afterEach(function (): void {
    File::deleteDirectory($this->bundleRoot);
});

it('never caches config while the release secrets are in the build environment', function (): void {
    $prebuild = collect(config('nativephp.prebuild'))->flatten()->implode("\n");

    expect($prebuild)->not->toMatch('/artisan\s+(config:cache|optimize)(\s|$)/m');
});

it('leaves a cached config out of the app it copies into the bundle', function (): void {
    $source = "{$this->bundleRoot}/source";
    File::ensureDirectoryExists("{$source}/bootstrap/cache");
    File::put("{$source}/bootstrap/cache/config.php", "<?php return ['apple_id_pass' => 'secret'];");
    File::put("{$source}/bootstrap/cache/packages.php", '<?php return [];');

    Builder::make(buildPath: "{$this->bundleRoot}/build", sourcePath: $source)->copyToBuildDirectory();

    expect("{$this->bundleRoot}/build/app/bootstrap/cache/config.php")->not->toBeFile()
        ->and("{$this->bundleRoot}/build/app/bootstrap/cache/packages.php")->toBeFile();
});

it('keeps the whole config cacheable for the first-launch optimize', function (): void {
    $exported = var_export(config()->all(), true);

    expect($exported)->not->toContain('Closure::__set_state')
        ->and(eval("return {$exported};"))->toBeArray();
});

it('checks both packaged macOS apps for build secrets after they are built', function (): void {
    $step = bundleSecretsStep();
    $names = array_column(Yaml::parseFile(base_path('.github/workflows/publish.yml'))['jobs']['build-macos']['steps'], 'name');

    expect($step)->not->toBeNull()
        ->and(array_search(BUNDLE_SECRETS_STEP, $names, true))
        ->toBeGreaterThan(array_search('Build and publish (x64)', $names, true))
        ->and($step['run'])->toContain('for APP in "$DIST/mac-arm64/Manuscript.app" "$DIST/mac/Manuscript.app"; do');

    foreach (array_keys(FAKE_BUILD_SECRETS) as $secret) {
        expect($step['env'][$secret])->toBe("\${{ secrets.{$secret} }}");
    }
});

it('passes packaged apps that carry no build secrets', function (): void {
    fakePackagedMacApps($this->bundleRoot);

    // An unset secret must not turn into an empty pattern that matches every file.
    $result = runBundleSecretsStep($this->bundleRoot, [...FAKE_BUILD_SECRETS, 'SENTRY_AUTH_TOKEN' => '']);

    expect($result->successful())->toBeTrue($result->output().$result->errorOutput())
        ->and($result->output())
        ->toContain('No build secrets bundled in nativephp/electron/dist/mac-arm64/Manuscript.app')
        ->toContain('No build secrets bundled in nativephp/electron/dist/mac/Manuscript.app');
});

it('fails when a cached config was bundled', function (): void {
    fakePackagedMacApps($this->bundleRoot);
    File::put("{$this->bundleRoot}/nativephp/electron/dist/mac/Manuscript.app/Contents/Resources/build/app/bootstrap/cache/config.php", '<?php return [];');

    $result = runBundleSecretsStep($this->bundleRoot);

    expect($result->failed())->toBeTrue()
        ->and($result->output())->toContain('::error::')->toContain('bootstrap/cache/config.php');
});

it('fails without echoing the value when a build secret was bundled', function (string $secret): void {
    fakePackagedMacApps($this->bundleRoot);
    $leakedFile = "{$this->bundleRoot}/nativephp/electron/dist/mac-arm64/Manuscript.app/Contents/Resources/app.asar";
    File::put($leakedFile, "\0binary\0".FAKE_BUILD_SECRETS[$secret]."\0");

    $result = runBundleSecretsStep($this->bundleRoot);

    expect($result->failed())->toBeTrue()
        ->and($result->output())->toContain('::error::')->toContain('Contents/Resources/app.asar')
        ->and($result->output().$result->errorOutput())->not->toContain(FAKE_BUILD_SECRETS[$secret]);
})->with(array_keys(FAKE_BUILD_SECRETS));

it('fails when a packaged app is missing', function (): void {
    fakePackagedMacApps($this->bundleRoot);
    File::deleteDirectory("{$this->bundleRoot}/nativephp/electron/dist/mac");

    $result = runBundleSecretsStep($this->bundleRoot);

    expect($result->failed())->toBeTrue()
        ->and($result->output())->toContain('::error::Expected packaged app is missing');
});
