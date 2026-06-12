<?php

declare(strict_types=1);

use App\Services\Speech\SpeechModelManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->modelDir = sys_get_temp_dir().'/manuscript-speech-test-'.uniqid();
    config(['speech.model_dir' => $this->modelDir]);
    Cache::forget('speech.model_download');
});

afterEach(function (): void {
    File::deleteDirectory($this->modelDir);
});

it('selects the large turbo model on apple silicon', function (): void {
    $manager = new SpeechModelManager(arch: 'arm64', osFamily: 'Darwin');

    expect($manager->variantKey())->toBe('large-v3-turbo-q5_0');
});

it('selects the small model on intel macs', function (): void {
    $manager = new SpeechModelManager(arch: 'x86_64', osFamily: 'Darwin');

    expect($manager->variantKey())->toBe('small-q5_1');
});

it('selects the small model on windows', function (): void {
    $manager = new SpeechModelManager(arch: 'AMD64', osFamily: 'Windows');

    expect($manager->variantKey())->toBe('small-q5_1');
});

it('resolves the model path inside the configured model directory', function (): void {
    $manager = new SpeechModelManager(arch: 'arm64', osFamily: 'Darwin');

    expect($manager->path())
        ->toBe($this->modelDir.'/'.config('speech.models.large-v3-turbo-q5_0.file'));
});

it('reports missing when no model file exists', function (): void {
    $manager = new SpeechModelManager(arch: 'arm64', osFamily: 'Darwin');

    expect($manager->isReady())->toBeFalse()
        ->and($manager->status()['state'])->toBe('missing');
});

it('reports ready when the model file exists', function (): void {
    $manager = new SpeechModelManager(arch: 'arm64', osFamily: 'Darwin');
    File::ensureDirectoryExists($this->modelDir);
    File::put($manager->path(), 'fake-model');

    expect($manager->isReady())->toBeTrue()
        ->and($manager->status()['state'])->toBe('ready');
});

it('reports download progress while downloading', function (): void {
    $manager = new SpeechModelManager(arch: 'arm64', osFamily: 'Darwin');
    $manager->markDownloading(42);

    $status = $manager->status();

    expect($status['state'])->toBe('downloading')
        ->and($status['progress'])->toBe(42);
});

it('reports a failed download with its error', function (): void {
    $manager = new SpeechModelManager(arch: 'arm64', osFamily: 'Darwin');
    $manager->markError('sha256 mismatch');

    $status = $manager->status();

    expect($status['state'])->toBe('error')
        ->and($status['error'])->toBe('sha256 mismatch');
});

it('prefers ready over stale download state', function (): void {
    $manager = new SpeechModelManager(arch: 'arm64', osFamily: 'Darwin');
    $manager->markDownloading(99);
    File::ensureDirectoryExists($this->modelDir);
    File::put($manager->path(), 'fake-model');

    expect($manager->status()['state'])->toBe('ready');
});

it('deletes the model file and clears download state', function (): void {
    $manager = new SpeechModelManager(arch: 'arm64', osFamily: 'Darwin');
    File::ensureDirectoryExists($this->modelDir);
    File::put($manager->path(), 'fake-model');
    $manager->markError('leftover');

    $manager->delete();

    expect(File::exists($manager->path()))->toBeFalse()
        ->and($manager->status()['state'])->toBe('missing');
});
