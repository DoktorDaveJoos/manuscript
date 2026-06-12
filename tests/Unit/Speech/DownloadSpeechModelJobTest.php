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
