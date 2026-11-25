<?php

declare(strict_types=1);

use App\Jobs\DownloadSpeechModelJob;
use App\Services\Speech\SpeechModelManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->modelDir = sys_get_temp_dir().'/manuscript-speech-job-'.uniqid();
    config(['speech.model_dir' => $this->modelDir]);
    Cache::forget('speech.model_download');
    $this->manager = new SpeechModelManager(arch: 'arm64', osFamily: 'Darwin');
});

afterEach(function (): void {
    File::deleteDirectory($this->modelDir);
});

it('downloads, verifies and activates the model', function (): void {
    $body = 'fake-model-bytes';
    config(['speech.models.large-v3-turbo-q5_0.sha256' => hash('sha256', $body)]);
    Http::fake(['huggingface.co/*' => Http::response($body)]);

    (new DownloadSpeechModelJob)->handle($this->manager);

    expect($this->manager->isReady())->toBeTrue()
        ->and(File::get($this->manager->path()))->toBe($body)
        ->and($this->manager->status()['state'])->toBe('ready');
});

it('rejects a download that fails checksum verification', function (): void {
    Http::fake(['huggingface.co/*' => Http::response('tampered-bytes')]);

    (new DownloadSpeechModelJob)->handle($this->manager);

    $status = $this->manager->status();

    expect($this->manager->isReady())->toBeFalse()
        ->and($status['state'])->toBe('error')
        ->and($status['error'])->toContain('verification')
        ->and(File::exists($this->manager->path().'.download'))->toBeFalse();
});

it('records an error when the download request fails', function (): void {
    Http::fake(['huggingface.co/*' => Http::response('', 503)]);

    (new DownloadSpeechModelJob)->handle($this->manager);

    expect($this->manager->isReady())->toBeFalse()
        ->and($this->manager->status()['state'])->toBe('error');
});

it('bounds uniqueFor and cleans up .download on failed()', function (): void {
    $token = $this->manager->beginDownload();
    $job = new DownloadSpeechModelJob($token);

    expect($job->uniqueFor)->toBeGreaterThan(0)
        ->and($job->timeout)->toBeLessThanOrEqual(1800);

    File::ensureDirectoryExists($this->modelDir);
    $tempPath = $this->manager->temporaryDownloadPath($token);
    File::put($tempPath, 'partial-bytes');

    $job->failed(new RuntimeException('Worker killed'));

    expect(File::exists($tempPath))->toBeFalse()
        ->and($this->manager->status()['state'])->toBe('error');
});

it('treats stale downloading state without heartbeat as interrupted error but spares queued jobs', function (): void {
    Cache::put('speech.model_download', [
        'token' => 'stale-token',
        'phase' => 'downloading',
        'progress' => 37,
        'updated_at' => time() - 300,
        'cancelled' => false,
    ], now()->addHours(2));

    expect($this->manager->status()['state'])->toBe('error')
        ->and($this->manager->isActivelyDownloading())->toBeFalse();

    // A queued download waiting >90s behind another single-worker job stays active.
    Cache::put('speech.model_download', [
        'token' => 'queued-token',
        'phase' => 'queued',
        'progress' => 0,
        'updated_at' => time() - 300,
        'cancelled' => false,
    ], now()->addHours(2));

    expect($this->manager->status()['state'])->toBe('downloading')
        ->and($this->manager->isActivelyDownloading())->toBeTrue();
});

it('isolates cancelled job cleanup from a restarted download attempt', function (): void {
    $tokenA = $this->manager->beginDownload();
    $jobA = new DownloadSpeechModelJob($tokenA);

    $this->manager->delete();
    $tokenB = $this->manager->beginDownload();
    $this->manager->markDownloading(25, $tokenB);

    File::ensureDirectoryExists($this->modelDir);
    $pathA = $this->manager->temporaryDownloadPath($tokenA);
    $pathB = $this->manager->temporaryDownloadPath($tokenB);
    File::put($pathA, 'old-bytes');
    File::put($pathB, 'new-bytes');

    // Old cancelled job failing must NOT delete the new download's file or overwrite state.
    $jobA->failed(new RuntimeException('Old download timed out'));

    expect(File::exists($pathA))->toBeFalse()
        ->and(File::exists($pathB))->toBeTrue()
        ->and($this->manager->status()['state'])->toBe('downloading')
        ->and($this->manager->status()['progress'])->toBe(25);
});
