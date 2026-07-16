<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

const RELEASE_SCRIPT = 'scripts/release.sh';

function runReleaseScriptCommand(string $workingDirectory, string $command): string
{
    $process = new Process(['bash', '-c', $command], $workingDirectory);
    $process->mustRun();

    return trim($process->getOutput());
}

it('finds the newest release tag across divergent branches', function (): void {
    $repository = sys_get_temp_dir().'/manuscript-release-script-'.uniqid();
    $files = new Filesystem;
    $files->ensureDirectoryExists($repository);

    try {
        runReleaseScriptCommand($repository, 'git init --quiet');
        runReleaseScriptCommand($repository, 'git config user.email release-test@example.com');
        runReleaseScriptCommand($repository, 'git config user.name "Release Test"');
        runReleaseScriptCommand($repository, 'git branch -M dev');
        runReleaseScriptCommand($repository, 'touch base && git add base && git commit --quiet -m "chore: base"');
        runReleaseScriptCommand($repository, 'git tag v0.9.0');
        runReleaseScriptCommand($repository, 'touch released && git add released && git commit --quiet -m "fix: released change"');
        runReleaseScriptCommand($repository, 'git switch --quiet -c main');
        runReleaseScriptCommand($repository, 'touch merge-only && git add merge-only && git commit --quiet -m "chore: main merge commit"');
        runReleaseScriptCommand($repository, 'git tag v0.10.1');
        runReleaseScriptCommand($repository, 'git switch --quiet dev');
        runReleaseScriptCommand($repository, 'touch next-fix && git add next-fix && git commit --quiet -m "fix(desktop): harden startup recovery"');

        $script = escapeshellarg(dirname(__DIR__, 2).'/'.RELEASE_SCRIPT);
        $latestTag = runReleaseScriptCommand($repository, "source {$script}; latest_release_tag");
        $commits = runReleaseScriptCommand($repository, "git log {$latestTag}..HEAD --format=%s");

        expect($latestTag)->toBe('v0.10.1')
            ->and($commits)->toBe('fix(desktop): harden startup recovery');
    } finally {
        $files->deleteDirectory($repository);
    }
});

it('classifies and cleans scoped conventional commits', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $script = escapeshellarg($projectRoot.'/'.RELEASE_SCRIPT);

    $featureCategory = runReleaseScriptCommand(
        $projectRoot,
        "source {$script}; commit_category 'feat(editor): add focus mode'",
    );
    $fixCategory = runReleaseScriptCommand(
        $projectRoot,
        "source {$script}; commit_category 'fix(desktop): harden startup recovery'",
    );
    $cleanSubject = runReleaseScriptCommand(
        $projectRoot,
        "source {$script}; clean_commit_subject 'fix(desktop): harden startup recovery'",
    );

    expect($featureCategory)->toBe('features')
        ->and($fixCategory)->toBe('fixes')
        ->and($cleanSubject)->toBe('harden startup recovery');
});
