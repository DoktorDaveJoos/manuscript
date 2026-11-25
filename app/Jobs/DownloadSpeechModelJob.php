<?php

namespace App\Jobs;

use App\Jobs\Concerns\ReportsJobFailures;
use App\Services\Speech\SpeechModelManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Streams the curated Whisper model into the app-data directory. The file is
 * written to a `.download` sidecar, sha256-verified against the pinned
 * upstream checksum, and only then moved into place — so a present model file
 * is always a verified one. Progress is published through the manager's cache
 * state for the settings UI to poll.
 */
class DownloadSpeechModelJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsJobFailures {
        ReportsJobFailures::failed as reportJobFailure;
    }

    public int $tries = 1;

    /**
     * Keep timeout below NativePHP's 1800s worker timeout so PHP cleans up
     * cleanly rather than being SIGKILL'd with a dangling lock and .download file.
     */
    public int $timeout = 1500;

    /**
     * Bound the ShouldBeUnique lock lifetime so a crashed worker or app quit
     * can never permanently brick future downloads on the file cache store.
     */
    public int $uniqueFor = 1800;

    public function __construct(public readonly ?string $downloadToken = null) {}

    public function handle(SpeechModelManager $manager): void
    {
        $definition = $manager->definition();
        $token = $this->downloadToken ?? $manager->beginDownload();
        $temporaryPath = $manager->temporaryDownloadPath($token);

        if ($manager->isCancelled($token)) {
            File::delete($temporaryPath);

            return;
        }

        File::ensureDirectoryExists($manager->modelDir());
        $manager->markDownloading(0, $token);

        try {
            $response = Http::timeout($this->timeout)
                ->connectTimeout(30)
                ->withOptions([
                    'sink' => $temporaryPath,
                    'progress' => $this->reportProgress($manager, $token),
                ])
                ->get($definition['url']);

            if ($manager->isCancelled($token)) {
                File::delete($temporaryPath);

                return;
            }

            if (! $response->successful()) {
                throw new RuntimeException("Model download failed (HTTP {$response->status()}).");
            }

            // Faked responses bypass Guzzle, so the sink never materializes in
            // tests — persist the body so verification exercises the real path.
            if (! File::exists($temporaryPath)) {
                File::put($temporaryPath, $response->body());
            }

            if (hash_file('sha256', $temporaryPath) !== $definition['sha256']) {
                throw new RuntimeException('Model download failed checksum verification.');
            }

            if ($manager->isCancelled($token)) {
                File::delete($temporaryPath);

                return;
            }

            File::move($temporaryPath, $manager->path());
            $manager->clearDownloadState();
        } catch (Throwable $e) {
            File::delete($temporaryPath);

            if ($manager->isCancelled($token)) {
                return;
            }

            $manager->markError($e instanceof RuntimeException
                ? $e->getMessage()
                : 'Model download failed. Check your connection and try again.');

            report($e);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $manager = app(SpeechModelManager::class);
        File::delete($manager->temporaryDownloadPath($this->downloadToken));

        // Only mutate shared error state and release the unique lock if this
        // job has not been superseded/cancelled by a newer download attempt.
        if (! $manager->isCancelled($this->downloadToken)) {
            $manager->markError('Model download timed out or failed unexpectedly. Please try again.');
            $manager->releaseUniqueLock();
        }

        $this->reportJobFailure($exception);
    }

    /**
     * Throttled Guzzle progress hook: updates cache whenever the visible
     * percentage changes, or every 5 seconds as a liveness heartbeat (even on
     * chunked responses without Content-Length).
     */
    private function reportProgress(SpeechModelManager $manager, string $token): callable
    {
        $lastPercent = 0;
        $lastHeartbeat = time();

        return function (int $total, int $downloaded) use ($manager, $token, &$lastPercent, &$lastHeartbeat): void {
            if ($manager->isCancelled($token)) {
                throw new RuntimeException('Model download cancelled.');
            }

            $now = time();
            $percent = $total > 0
                ? min(99, (int) floor($downloaded / $total * 100))
                : $lastPercent;

            if ($percent !== $lastPercent || ($now - $lastHeartbeat) >= 5) {
                $lastPercent = $percent;
                $lastHeartbeat = $now;
                $manager->markDownloading($percent, $token);
            }
        };
    }
}
