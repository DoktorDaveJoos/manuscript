<?php

namespace App\Services\Speech;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

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

        if (is_array($download)) {
            return ['state' => 'downloading', 'progress' => (int) ($download['progress'] ?? 0), ...$base];
        }

        return ['state' => 'missing', ...$base];
    }

    public function markDownloading(int $progress = 0): void
    {
        Cache::put(self::DOWNLOAD_CACHE_KEY, ['progress' => $progress], now()->addDay());
    }

    public function markError(string $message): void
    {
        Cache::put(self::DOWNLOAD_CACHE_KEY, ['error' => $message], now()->addDay());
    }

    public function clearDownloadState(): void
    {
        Cache::forget(self::DOWNLOAD_CACHE_KEY);
    }

    public function delete(): void
    {
        File::delete($this->path());
        $this->clearDownloadState();
    }
}
