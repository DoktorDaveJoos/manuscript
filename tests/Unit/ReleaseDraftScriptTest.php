<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

const RELEASE_DRAFT_SCRIPT = 'scripts/release-draft.sh';

/**
 * A stand-in for the `gh` CLI. It serves releases.json, assets.json and
 * asset-<id> from a state directory, logs every call, and turns
 * `gh release create` into a new draft, so scripts/release-draft.sh can be
 * driven end to end without talking to GitHub.
 */
const FAKE_GH_CLI = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
state="$FAKE_GH_STATE"
echo "$*" >> "$state/calls.log"
case "$*" in
    "api --paginate repos/{owner}/{repo}/releases?per_page=100" | "api repos/{owner}/{repo}/releases")
        cat "$state/releases.json"
        ;;
    "release create "*)
        jq --arg tag "$3" '. + [{"id": 900, "tag_name": $tag, "draft": true}]' "$state/releases.json" > "$state/releases.next"
        mv "$state/releases.next" "$state/releases.json"
        echo "https://github.com/DoktorDaveJoos/manuscript/releases/tag/$3"
        ;;
    "api --paginate repos/{owner}/{repo}/releases/"*"/assets?per_page=100")
        cat "$state/assets.json"
        ;;
    "api -H Accept: application/octet-stream repos/{owner}/{repo}/releases/assets/"*)
        endpoint="${!#}"
        cat "$state/asset-${endpoint##*/}"
        ;;
    *)
        echo "unexpected gh call: $*" >&2
        exit 64
        ;;
esac
BASH;

/**
 * @param  list<array{id: int, tag_name: string, draft: bool}>  $releases
 */
function makeFakeGhState(array $releases = []): string
{
    $state = sys_get_temp_dir().'/manuscript-release-draft-'.uniqid();
    $files = new Filesystem;

    $files->ensureDirectoryExists($state.'/bin');
    $files->put($state.'/bin/gh', FAKE_GH_CLI);
    chmod($state.'/bin/gh', 0755);
    $files->put($state.'/releases.json', json_encode($releases, JSON_THROW_ON_ERROR));
    $files->put($state.'/calls.log', '');

    return $state;
}

/**
 * @param  list<string>  $arguments
 */
function runReleaseDraftScript(string $state, array $arguments): Process
{
    $process = new Process(
        ['bash', dirname(__DIR__, 2).'/'.RELEASE_DRAFT_SCRIPT, ...$arguments],
        $state,
        [
            'PATH' => $state.'/bin:'.getenv('PATH'),
            'FAKE_GH_STATE' => $state,
            'RELEASE_DRAFT_POLL_ATTEMPTS' => '2',
            'RELEASE_DRAFT_POLL_SECONDS' => '0',
        ],
    );
    $process->run();

    return $process;
}

/**
 * An update manifest in the shape electron-builder writes (latest-mac.yml /
 * latest.yml), with the first file as the legacy path/sha512.
 *
 * @param  array<string, array{sha512: string, size: int}>  $files
 */
function electronBuilderManifest(string $version, array $files): string
{
    $yaml = "version: {$version}\nfiles:\n";

    foreach ($files as $url => $file) {
        $yaml .= "  - url: {$url}\n    sha512: {$file['sha512']}\n    size: {$file['size']}\n";
    }

    $first = array_key_first($files);

    return $yaml."path: {$first}\nsha512: {$files[$first]['sha512']}\nreleaseDate: '2026-10-23T12:00:00.000Z'\n";
}

/**
 * @return array{sha512: string, size: int}
 */
function fakeUpdateFile(int $size): array
{
    return ['sha512' => base64_encode(hash('sha512', (string) $size, true)), 'size' => $size];
}

/**
 * Puts one draft for v1.2.3 into the fake GitHub, holding every release
 * artifact with the given update manifests.
 *
 * @param  array<string, array{sha512: string, size: int}>  $macFiles
 * @param  list<array{id: int, tag_name: string, draft: bool}>  $extraReleases
 */
function seedDraftRelease(string $state, array $macFiles, array $extraReleases = [], string $partialAsset = ''): void
{
    $files = new Filesystem;
    $windowsFiles = ['Manuscript-setup.exe' => fakeUpdateFile(500)];
    $macManifest = electronBuilderManifest('1.2.3', $macFiles);
    $windowsManifest = electronBuilderManifest('1.2.3', $windowsFiles);

    $sizes = [
        'Manuscript-x64.zip' => 400,
        'Manuscript-arm64.zip' => 300,
        'Manuscript-x64.dmg' => 200,
        'Manuscript-arm64.dmg' => 100,
        'Manuscript-setup.exe' => 500,
        'Manuscript-setup.exe.blockmap' => 50,
    ];

    $assets = [];
    $id = 1000;
    foreach ($sizes as $name => $size) {
        $assets[] = ['id' => ++$id, 'name' => $name, 'state' => $name === $partialAsset ? 'starter' : 'uploaded', 'size' => $size];
    }
    $assets[] = ['id' => 2001, 'name' => 'latest-mac.yml', 'state' => 'uploaded', 'size' => strlen($macManifest)];
    $assets[] = ['id' => 2002, 'name' => 'latest.yml', 'state' => 'uploaded', 'size' => strlen($windowsManifest)];

    $files->put($state.'/releases.json', json_encode([
        ['id' => 501, 'tag_name' => 'v1.2.3', 'draft' => true],
        ...$extraReleases,
    ], JSON_THROW_ON_ERROR));
    $files->put($state.'/assets.json', json_encode($assets, JSON_THROW_ON_ERROR));
    $files->put($state.'/asset-2001', $macManifest);
    $files->put($state.'/asset-2002', $windowsManifest);
}

/**
 * @return array<string, array{sha512: string, size: int}>
 */
function bothArchMacFiles(): array
{
    return [
        'Manuscript-x64.zip' => fakeUpdateFile(400),
        'Manuscript-arm64.zip' => fakeUpdateFile(300),
        'Manuscript-x64.dmg' => fakeUpdateFile(200),
        'Manuscript-arm64.dmg' => fakeUpdateFile(100),
    ];
}

afterEach(function (): void {
    if (isset($this->state)) {
        (new Filesystem)->deleteDirectory($this->state);
    }
});

describe('prepare', function (): void {
    it('creates the draft release before any build runs', function (): void {
        $this->state = makeFakeGhState([['id' => 7, 'tag_name' => 'v1.2.2', 'draft' => false]]);

        $process = runReleaseDraftScript($this->state, ['prepare', 'v1.2.3']);
        $calls = (string) file_get_contents($this->state.'/calls.log');

        expect($process->getExitCode())->toBe(0, $process->getErrorOutput().$process->getOutput())
            ->and($calls)->toContain('release create v1.2.3 --draft --verify-tag')
            ->and($process->getOutput())->toContain('Draft 900 is the only release for v1.2.3');
    });

    it('reuses the existing draft when a failed run is retried', function (): void {
        $this->state = makeFakeGhState([['id' => 42, 'tag_name' => 'v1.2.3', 'draft' => true]]);

        $process = runReleaseDraftScript($this->state, ['prepare', 'v1.2.3']);

        expect($process->getExitCode())->toBe(0, $process->getErrorOutput().$process->getOutput())
            ->and((string) file_get_contents($this->state.'/calls.log'))->not->toContain('release create')
            ->and($process->getOutput())->toContain('Draft 42 is the only release for v1.2.3');
    });

    it('refuses to build into a tag that already has several releases', function (): void {
        $this->state = makeFakeGhState([
            ['id' => 341439810, 'tag_name' => 'v1.2.3', 'draft' => true],
            ['id' => 341439811, 'tag_name' => 'v1.2.3', 'draft' => true],
        ]);

        $process = runReleaseDraftScript($this->state, ['prepare', 'v1.2.3']);

        expect($process->getExitCode())->not->toBe(0)
            ->and($process->getOutput())->toContain('341439810')->toContain('341439811')
            ->and((string) file_get_contents($this->state.'/calls.log'))->not->toContain('release create');
    });

    it('refuses to upload over a release that is already published', function (): void {
        $this->state = makeFakeGhState([['id' => 42, 'tag_name' => 'v1.2.3', 'draft' => false]]);

        $process = runReleaseDraftScript($this->state, ['prepare', 'v1.2.3']);

        expect($process->getExitCode())->not->toBe(0)
            ->and($process->getOutput())->toContain('already published');
    });

    it('rejects anything that is not a release tag', function (): void {
        $this->state = makeFakeGhState();

        $process = runReleaseDraftScript($this->state, ['prepare', 'dev']);

        expect($process->getExitCode())->not->toBe(0)
            ->and((string) file_get_contents($this->state.'/calls.log'))->toBe('');
    });
});

describe('mac-manifest', function (): void {
    beforeEach(function (): void {
        $this->state = makeFakeGhState();
        $this->dist = $this->state.'/dist';
        $files = new Filesystem;
        $files->ensureDirectoryExists($this->dist);

        $this->built = [];
        foreach (['Manuscript-x64.zip', 'Manuscript-arm64.zip', 'Manuscript-x64.dmg', 'Manuscript-arm64.dmg'] as $name) {
            $files->put($this->dist.'/'.$name, random_bytes(random_int(2048, 4096)));
            $this->built[$name] = [
                'sha512' => base64_encode((string) hash_file('sha512', $this->dist.'/'.$name, true)),
                'size' => (int) filesize($this->dist.'/'.$name),
            ];
        }

        $files->put($this->state.'/latest-mac-arm64.yml', electronBuilderManifest('1.2.3', [
            'Manuscript-arm64.zip' => $this->built['Manuscript-arm64.zip'],
            'Manuscript-arm64.dmg' => $this->built['Manuscript-arm64.dmg'],
        ]));
        $files->put($this->state.'/latest-mac-x64.yml', electronBuilderManifest('1.2.3', [
            'Manuscript-x64.zip' => $this->built['Manuscript-x64.zip'],
            'Manuscript-x64.dmg' => $this->built['Manuscript-x64.dmg'],
        ]));
    });

    it('writes one manifest covering both arches in electron-builder order', function (): void {
        $process = runReleaseDraftScript($this->state, [
            'mac-manifest', $this->dist, '1.2.3', $this->state.'/latest-mac.yml',
            $this->state.'/latest-mac-arm64.yml', $this->state.'/latest-mac-x64.yml',
        ]);

        expect($process->getExitCode())->toBe(0, $process->getErrorOutput().$process->getOutput());

        $manifest = (string) file_get_contents($this->state.'/latest-mac.yml');
        $expected = electronBuilderManifest('1.2.3', [
            'Manuscript-x64.zip' => $this->built['Manuscript-x64.zip'],
            'Manuscript-arm64.zip' => $this->built['Manuscript-arm64.zip'],
            'Manuscript-x64.dmg' => $this->built['Manuscript-x64.dmg'],
            'Manuscript-arm64.dmg' => $this->built['Manuscript-arm64.dmg'],
        ]);

        expect($manifest)->toMatch("/^releaseDate: '\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}\\.\\d{3}Z'$/m")
            ->and(preg_replace('/^releaseDate: .*$/m', "releaseDate: '2026-10-23T12:00:00.000Z'", $manifest))->toBe($expected);
    });

    it('fails when the per-arch manifests do not cover both arches', function (): void {
        $process = runReleaseDraftScript($this->state, [
            'mac-manifest', $this->dist, '1.2.3', $this->state.'/latest-mac.yml',
            $this->state.'/latest-mac-x64.yml',
        ]);

        expect($process->getExitCode())->not->toBe(0)
            ->and($process->getOutput())->toContain('Manuscript-arm64.zip')
            ->and($this->state.'/latest-mac.yml')->not->toBeFile();
    });

    it('fails when a published entry does not match the file on disk', function (): void {
        (new Filesystem)->put($this->state.'/latest-mac-arm64.yml', electronBuilderManifest('1.2.3', [
            'Manuscript-arm64.zip' => fakeUpdateFile($this->built['Manuscript-arm64.zip']['size']),
            'Manuscript-arm64.dmg' => $this->built['Manuscript-arm64.dmg'],
        ]));

        $process = runReleaseDraftScript($this->state, [
            'mac-manifest', $this->dist, '1.2.3', $this->state.'/latest-mac.yml',
            $this->state.'/latest-mac-arm64.yml', $this->state.'/latest-mac-x64.yml',
        ]);

        expect($process->getExitCode())->not->toBe(0)
            ->and($process->getOutput())->toContain('Manuscript-arm64.zip')
            ->and($this->state.'/latest-mac.yml')->not->toBeFile();
    });
});

describe('verify', function (): void {
    it('passes a single draft holding every artifact and complete manifests', function (): void {
        $this->state = makeFakeGhState();
        seedDraftRelease($this->state, bothArchMacFiles());

        $process = runReleaseDraftScript($this->state, ['verify', 'v1.2.3']);

        expect($process->getExitCode())->toBe(0, $process->getErrorOutput().$process->getOutput())
            ->and($process->getOutput())->toContain('latest-mac.yml covers Manuscript-arm64.zip');
    });

    it('rejects a single-arch macOS manifest like the one v0.10.2 shipped', function (): void {
        $this->state = makeFakeGhState();
        seedDraftRelease($this->state, [
            'Manuscript-x64.zip' => fakeUpdateFile(400),
            'Manuscript-x64.dmg' => fakeUpdateFile(200),
        ]);

        $process = runReleaseDraftScript($this->state, ['verify', 'v1.2.3']);

        expect($process->getExitCode())->not->toBe(0)
            ->and($process->getOutput())->toContain('latest-mac.yml does not list Manuscript-arm64.zip');
    });

    it('rejects a manifest entry whose size disagrees with the uploaded asset', function (): void {
        $this->state = makeFakeGhState();
        seedDraftRelease($this->state, [...bothArchMacFiles(), 'Manuscript-arm64.zip' => fakeUpdateFile(299)]);

        $process = runReleaseDraftScript($this->state, ['verify', 'v1.2.3']);

        expect($process->getExitCode())->not->toBe(0)
            ->and($process->getOutput())->toContain('Manuscript-arm64.zip');
    });

    it('rejects a tag whose artifacts were split across two drafts', function (): void {
        $this->state = makeFakeGhState();
        seedDraftRelease($this->state, bothArchMacFiles(), [['id' => 502, 'tag_name' => 'v1.2.3', 'draft' => true]]);

        $process = runReleaseDraftScript($this->state, ['verify', 'v1.2.3']);

        expect($process->getExitCode())->not->toBe(0)
            ->and($process->getOutput())->toContain('found 2')->toContain('502');
    });

    it('rejects assets left half-uploaded', function (): void {
        $this->state = makeFakeGhState();
        seedDraftRelease($this->state, bothArchMacFiles(), partialAsset: 'Manuscript-setup.exe.blockmap');

        $process = runReleaseDraftScript($this->state, ['verify', 'v1.2.3']);

        expect($process->getExitCode())->not->toBe(0)
            ->and($process->getOutput())->toContain('Manuscript-setup.exe.blockmap');
    });
});
