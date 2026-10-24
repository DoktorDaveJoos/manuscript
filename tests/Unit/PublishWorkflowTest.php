<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * @return array<string, mixed>
 */
function workflowDefinition(string $file): array
{
    return Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/'.$file);
}

/**
 * @param  array<string, mixed>  $job
 * @return array<string, array<string, mixed>>
 */
function workflowStepsByName(array $job): array
{
    return collect($job['steps'])->keyBy('name')->all();
}

it('creates the one draft release before any build can publish', function (): void {
    $jobs = workflowDefinition('publish.yml')['jobs'];

    expect($jobs)->toHaveKey('prepare-release')
        ->and($jobs['prepare-release']['if'])->toBe("github.event_name == 'push'")
        ->and(collect($jobs['prepare-release']['steps'])->pluck('run')->implode("\n"))
        ->toContain('bash scripts/release-draft.sh prepare "$GITHUB_REF_NAME"')
        ->and($jobs['build-macos']['needs'])->toBe('prepare-release')
        ->and($jobs['build-windows']['needs'])->toBe('prepare-release');
});

it('publishes one macOS update manifest that covers both arches', function (): void {
    $steps = workflowDefinition('publish.yml')['jobs']['build-macos']['steps'];
    $names = array_column($steps, 'name');
    $byName = workflowStepsByName(['steps' => $steps]);

    expect(array_search('Keep the arm64 update manifest', $names, true))
        ->toBeGreaterThan(array_search('Build and publish (arm64)', $names, true))
        ->toBeLessThan(array_search('Build and publish (x64)', $names, true))
        ->and(array_search('Merge the macOS update manifest', $names, true))
        ->toBeGreaterThan(array_search('Build and publish (x64)', $names, true))
        ->and(end($names))->toBe('Publish the merged macOS update manifest')
        ->and($byName['Merge the macOS update manifest']['run'])
        ->toContain('bash scripts/release-draft.sh mac-manifest')
        ->toContain('"$RUNNER_TEMP/latest-mac-arm64.yml" "$DIST/latest-mac.yml"')
        ->and($byName['Publish the merged macOS update manifest']['if'])->toBe("github.event_name == 'push'")
        ->and($byName['Publish the merged macOS update manifest']['run'])
        ->toContain('gh release upload "$GITHUB_REF_NAME" "$RUNNER_TEMP/latest-mac.yml" --clobber');
});

it('fails the build when notarization silently failed', function (): void {
    $steps = workflowStepsByName(workflowDefinition('publish.yml')['jobs']['build-macos']);

    expect($steps['Verify the apps are notarized']['run'])
        ->toContain('for APP in "$DIST/mac-arm64/Manuscript.app" "$DIST/mac/Manuscript.app"; do')
        ->toContain('xcrun stapler validate "$APP"');
});

it('verifies the finished draft before it can be promoted', function (): void {
    $jobs = workflowDefinition('publish.yml')['jobs'];

    expect($jobs['verify-release']['needs'])->toBe(['build-macos', 'build-windows'])
        ->and($jobs['verify-release']['if'])->toBe("github.event_name == 'push'")
        ->and(collect($jobs['verify-release']['steps'])->pluck('run')->implode("\n"))
        ->toContain('bash scripts/release-draft.sh verify "$GITHUB_REF_NAME"');
});

it('never runs two publishers for the same tag at once', function (): void {
    $concurrency = workflowDefinition('publish.yml')['concurrency'];

    expect($concurrency['group'])->toBe('publish-${{ github.ref }}')
        ->and($concurrency['cancel-in-progress'])->toBe("\${{ github.event_name == 'pull_request' }}");
});

it('dry-runs the release build on pipeline pull requests without publishing', function (): void {
    $workflow = workflowDefinition('publish.yml');
    $noTokenUnlessTagPush = "\${{ github.event_name == 'push' && secrets.GITHUB_TOKEN || '' }}";

    expect($workflow['on']['push']['tags'])->toBe(['v*'])
        ->and($workflow['on']['pull_request']['paths'])->toContain('.github/workflows/publish.yml', 'scripts/release-draft.sh')
        ->and($workflow['env']['NATIVE_COMMAND'])->toBe("\${{ github.event_name == 'push' && 'publish' || 'build' }}");

    $buildSteps = collect($workflow['jobs'])
        ->only(['build-macos', 'build-windows'])
        ->flatMap(fn (array $job): array => $job['steps'])
        ->filter(fn (array $step): bool => str_starts_with($step['name'], 'Build and publish'));

    expect($buildSteps)->toHaveCount(3);

    foreach ($buildSteps as $step) {
        expect($step['run'])->toContain('php artisan native:${{ env.NATIVE_COMMAND }}')
            ->and($step['env']['GH_TOKEN'])->toBe($noTokenUnlessTagPush)
            ->and($step['env']['GITHUB_TOKEN'])->toBe($noTokenUnlessTagPush)
            ->and($step['env']['SENTRY_AUTH_TOKEN'])->toBe("\${{ github.event_name == 'push' && secrets.SENTRY_AUTH_TOKEN || '' }}");
    }

    // electron-builder skips signing whenever GITHUB_BASE_REF is set, which
    // would fail the codesign checks of every pull request dry run.
    foreach (['Build and publish (arm64)', 'Build and publish (x64)'] as $macStep) {
        expect(workflowStepsByName($workflow['jobs']['build-macos'])[$macStep]['env']['CSC_FOR_PULL_REQUEST'])->toBe('true');
    }
});

it('lints, typechecks and builds every push and pull request to dev', function (string $file): void {
    $triggers = workflowDefinition($file)['on'];

    expect($triggers['push']['branches'])->toContain('dev')
        ->and($triggers['pull_request']['branches'])->toContain('dev');
})->with(['lint.yml', 'tests.yml']);

it('builds the frontend in the linter so a broken vite build fails before release', function (): void {
    $runs = collect(workflowDefinition('lint.yml')['jobs']['quality']['steps'])->pluck('run')->filter()->implode("\n");

    expect($runs)->toContain('npm run types:check')->toContain('npm run build');
});

it('makes release:promote run the same draft check as the publish workflow', function (): void {
    $promote = (string) file_get_contents(dirname(__DIR__, 2).'/scripts/release-promote.sh');

    expect($promote)->toContain('release-draft.sh" verify "$TAG"')
        ->not->toContain('REQUIRED_ARTIFACTS=(');
});
