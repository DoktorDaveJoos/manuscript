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
    use Dispatchable, InteractsWithQueue, Queueable, ReportsJobFailures;

    public int $tries = 1;

    public int $timeout = 3600;

    public function handle(SpeechModelManager $manager): void
    {
        $definition = $manager->definition();
        $temporaryPath = $manager->path().'.download';

        File::ensureDirectoryExists($manager->modelDir());
        $manager->markDownloading(0);

        try {
            $response = Http::timeout($this->timeout)
                ->connectTimeout(30)
                ->withOptions([
                    'sink' => $temporaryPath,
                    'progress' => $this->reportProgress($manager),
                ])
                ->get($definition['url']);

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

            File::move($temporaryPath, $manager->path());
            $manager->clearDownloadState();
        } catch (Throwable $e) {
            File::delete($temporaryPath);
            $manager->markError($e instanceof RuntimeException
                ? $e->getMessage()
                : 'Model download failed. Check your connection and try again.');

            report($e);
        }
    }

    /**
     * Throttled Guzzle progress hook: only touches the cache when the visible
     * percentage actually changes.
     */
    private function reportProgress(SpeechModelManager $manager): callable
    {
        $lastPercent = -1;

        return function (int $total, int $downloaded) use ($manager, &$lastPercent): void {
            if ($total <= 0) {
                return;
            }

            $percent = (int) floor($downloaded / $total * 100);

            if ($percent !== $lastPercent) {
                $lastPercent = $percent;
                $manager->markDownloading($percent);
            }
        };
    }
}
