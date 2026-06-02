<?php

use App\Jobs\Editorial\FinalizeEditorialReviewJob;
use App\Jobs\RunEditorialReviewJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

uses(TestCase::class);

/*
|--------------------------------------------------------------------------
| Queue timeout / retry_after invariant
|--------------------------------------------------------------------------
|
| The database queue treats `retry_after` as the reservation-lock duration:
| once a reserved job is older than retry_after seconds it becomes available
| again. If a job's `$timeout` exceeds retry_after, the still-running job is
| picked up a second time and — because these jobs use `tries = 1` — Laravel
| throws "<Job> has been attempted too many times".
|
| So retry_after MUST be greater than the NativePHP worker timeout AND greater
| than every queued job's `$timeout`. This test would have caught
| RunEditorialReviewJob ($timeout = 1800) running under retry_after = 90.
|
*/

/**
 * Map every queued job class under app/Jobs to its default `$timeout`.
 *
 * @return array<class-string, int>
 */
function queuedJobTimeouts(): array
{
    $timeouts = [];

    foreach ((new Finder)->files()->in(app_path('Jobs'))->name('*.php') as $file) {
        $class = 'App\\Jobs\\'.Str::of($file->getRealPath())
            ->after('Jobs'.DIRECTORY_SEPARATOR)
            ->replace(DIRECTORY_SEPARATOR, '\\')
            ->beforeLast('.php')
            ->value();

        if (! class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->implementsInterface(ShouldQueue::class) || $reflection->isAbstract()) {
            continue;
        }

        $timeout = $reflection->getDefaultProperties()['timeout'] ?? null;

        if ($timeout !== null) {
            $timeouts[$class] = $timeout;
        }
    }

    return $timeouts;
}

it('keeps queue retry_after safely above every job and worker timeout', function () {
    $retryAfter = config('queue.connections.database.retry_after');
    $workerTimeout = config('nativephp.queue_workers.default.timeout');

    // Require headroom, not just retry_after > timeout: a job killed at its
    // timeout must not be re-reservable before the worker releases it. Enforcing
    // a buffer stops the margin silently eroding to zero as job timeouts grow.
    $buffer = 30;

    $violations = [];

    if ($retryAfter < $workerTimeout + $buffer) {
        $violations[] = "NativePHP worker timeout ({$workerTimeout}s) within {$buffer}s of retry_after ({$retryAfter}s)";
    }

    foreach (queuedJobTimeouts() as $job => $timeout) {
        if ($retryAfter < $timeout + $buffer) {
            $violations[] = "{$job}::\$timeout ({$timeout}s) within {$buffer}s of retry_after ({$retryAfter}s)";
        }
    }

    expect($violations)->toBe([]);
});

it('discovers the long-running jobs it is meant to guard', function () {
    $timeouts = queuedJobTimeouts();

    // The editorial review is now decomposed: the orchestrator just builds the
    // batch (short), while synthesis runs in the bounded finalize job.
    expect($timeouts)->toHaveKey(FinalizeEditorialReviewJob::class)
        ->and($timeouts[FinalizeEditorialReviewJob::class])->toBe(1800)
        ->and($timeouts[RunEditorialReviewJob::class])->toBe(60);
});
