<?php

namespace App\Services\Speech;

use App\Jobs\DownloadSpeechModelJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Resolves which Whisper model this machine should use and tracks its
 * lifecycle (missing → downloading → ready / error). File presence is the
 * source of truth for "ready": the download job only moves a model into
 * place after its sha256 has been verified, so an existing file is a valid
 * one. No database state is involved.
 */
class SpeechModelManager
{
    private const DOWNLOAD_CACHE_KEY = 'speech.model_download';

    /**
     * Seconds without a progress heartbeat while actively downloading before
     * the transfer is treated as crashed/stale.
     */
    private const STALE_DOWNLOADING_SECONDS = 90;

    /**
     * Seconds a newly queued download may wait behind other single-worker jobs
     * (e.g. an editorial review) before being considered orphaned.
     */
    private const STALE_QUEUED_SECONDS = 1800;

    public function __construct(
        private readonly ?string $arch = null,
        private readonly ?string $osFamily = null,
    ) {}

    /**
     * Curated per hardware: Apple Silicon handles the large turbo model via
     * Metal faster than realtime; every CPU-bound platform gets small.
     */
    public function variantKey(): string
    {
        $arch = $this->arch ?? php_uname('m');
        $osFamily = $this->osFamily ?? PHP_OS_FAMILY;

        return $osFamily === 'Darwin' && $arch === 'arm64'
            ? 'large-v3-turbo-q5_0'
            : 'small-q5_1';
    }

    /**
     * @return array{label: string, file: string, url: string, sha256: string, size_bytes: int}
     */
    public function definition(): array
    {
        return config("speech.models.{$this->variantKey()}");
    }

    public function modelDir(): string
    {
        return config('speech.model_dir');
    }

    public function path(): string
    {
        return $this->modelDir().DIRECTORY_SEPARATOR.$this->definition()['file'];
    }

    public function temporaryDownloadPath(?string $token = null): string
    {
        if ($token !== null && $token !== '') {
            return $this->path().'.'.$token.'.download';
        }

        return $this->path().'.download';
    }

    public function isReady(): bool
    {
        return File::exists($this->path());
    }

    /**
     * @return array{state: 'ready'|'downloading'|'error'|'missing', variant: string, label: string, size_bytes: int, progress?: int, error?: string}
     */
    public function status(): array
    {
        $definition = $this->definition();

        $base = [
            'variant' => $this->variantKey(),
            'label' => $definition['label'],
            'size_bytes' => $definition['size_bytes'],
        ];

        if ($this->isReady()) {
            return ['state' => 'ready', ...$base];
        }

        $download = Cache::get(self::DOWNLOAD_CACHE_KEY);

        if (is_array($download) && isset($download['error'])) {
            return ['state' => 'error', 'error' => $download['error'], ...$base];
        }

        if (is_array($download) && ! ($download['cancelled'] ?? false)) {
            if ($this->isStaleDownloadRecord($download)) {
                $this->clearDownloadState();
                $this->releaseUniqueLock();

                return ['state' => 'error', 'error' => 'Model download was interrupted. Please try again.', ...$base];
            }

            return ['state' => 'downloading', 'progress' => (int) ($download['progress'] ?? 0), ...$base];
        }

        return ['state' => 'missing', ...$base];
    }

    public function isActivelyDownloading(): bool
    {
        $download = Cache::get(self::DOWNLOAD_CACHE_KEY);

        if (! is_array($download) || isset($download['error']) || ($download['cancelled'] ?? false)) {
            return false;
        }

        return ! $this->isStaleDownloadRecord($download);
    }

    public function beginDownload(): string
    {
        $token = (string) Str::uuid();

        Cache::put(self::DOWNLOAD_CACHE_KEY, [
            'token' => $token,
            'phase' => 'queued',
            'progress' => 0,
            'updated_at' => time(),
            'cancelled' => false,
        ], now()->addHours(2));

        return $token;
    }

    public function markDownloading(int $progress = 0, ?string $token = null): void
    {
        $existing = Cache::get(self::DOWNLOAD_CACHE_KEY);
        $currentToken = $token ?? (is_array($existing) ? ($existing['token'] ?? null) : null);

        if ($token !== null && $this->isCancelled($token)) {
            return;
        }

        Cache::put(self::DOWNLOAD_CACHE_KEY, [
            'token' => $currentToken,
            'phase' => 'downloading',
            'progress' => $progress,
            'updated_at' => time(),
            'cancelled' => false,
        ], now()->addHours(2));
    }

    public function isCancelled(?string $token): bool
    {
        if ($token === null) {
            return false;
        }

        $download = Cache::get(self::DOWNLOAD_CACHE_KEY);

        if (! is_array($download)) {
            return true;
        }

        if (($download['cancelled'] ?? false) === true) {
            return true;
        }

        return isset($download['token']) && $download['token'] !== $token;
    }

    public function markError(string $message): void
    {
        Cache::put(self::DOWNLOAD_CACHE_KEY, [
            'error' => $message,
            'updated_at' => time(),
        ], now()->addDay());
    }

    public function clearDownloadState(): void
    {
        Cache::forget(self::DOWNLOAD_CACHE_KEY);
    }

    public function releaseUniqueLock(): void
    {
        Cache::lock('laravel_unique_job:'.DownloadSpeechModelJob::class.':')->forceRelease();
    }

    public function delete(): void
    {
        $existing = Cache::get(self::DOWNLOAD_CACHE_KEY);
        if (is_array($existing) && isset($existing['token'])) {
            // Signal any running download job to abort before removing files.
            Cache::put(self::DOWNLOAD_CACHE_KEY, [
                'token' => $existing['token'],
                'cancelled' => true,
                'updated_at' => time(),
            ], now()->addMinutes(5));
        } else {
            $this->clearDownloadState();
        }

        File::delete($this->path());
        File::delete($this->temporaryDownloadPath());

        foreach (glob($this->path().'.*.download') ?: [] as $stalePart) {
            File::delete($stalePart);
        }

        $this->releaseUniqueLock();
    }

    /**
     * @param  array<string, mixed>  $download
     */
    private function isStaleDownloadRecord(array $download): bool
    {
        $updatedAt = $download['updated_at'] ?? null;
        if (! is_int($updatedAt)) {
            return false;
        }

        $limit = ($download['phase'] ?? 'downloading') === 'queued'
            ? self::STALE_QUEUED_SECONDS
            : self::STALE_DOWNLOADING_SECONDS;

        return (time() - $updatedAt) > $limit;
    }
}
