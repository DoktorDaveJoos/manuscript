<?php

use App\Jobs\DownloadSpeechModelJob;
use App\Services\Speech\SpeechModelManager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->modelDir = sys_get_temp_dir().'/manuscript-speech-model-ctl-'.uniqid();
    $this->extrasDir = sys_get_temp_dir().'/manuscript-speech-model-extras-'.uniqid();
    config([
        'speech.model_dir' => $this->modelDir,
        'speech.extras_dir' => $this->extrasDir,
    ]);
    Cache::forget('speech.model_download');
});

afterEach(function (): void {
    File::deleteDirectory($this->modelDir);
    File::deleteDirectory($this->extrasDir);
});

function speechModelFile(): string
{
    $manager = app(SpeechModelManager::class);
    File::ensureDirectoryExists($manager->modelDir());
    File::put($manager->path(), 'fake-model');

    return $manager->path();
}

test('show reports the curated model status', function () {
    $this->getJson(route('speech.model.show'))
        ->assertOk()
        ->assertJsonStructure(['state', 'variant', 'label', 'size_bytes'])
        ->assertJson(['state' => 'missing']);
});

test('store dispatches the download job and reports downloading', function () {
    Bus::fake();

    $this->postJson(route('speech.model.store'))
        ->assertOk()
        ->assertJson(['state' => 'downloading']);

    Bus::assertDispatched(DownloadSpeechModelJob::class);
});

test('store does not re-dispatch when the model is already ready', function () {
    Bus::fake();
    speechModelFile();

    $this->postJson(route('speech.model.store'))
        ->assertOk()
        ->assertJson(['state' => 'ready']);

    Bus::assertNotDispatched(DownloadSpeechModelJob::class);
});

test('store retries after a failed download', function () {
    Bus::fake();
    app(SpeechModelManager::class)->markError('checksum mismatch');

    $this->postJson(route('speech.model.store'))
        ->assertOk()
        ->assertJson(['state' => 'downloading']);

    Bus::assertDispatched(DownloadSpeechModelJob::class);
});

test('speech_ready shared prop is false without binary and model', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('speech_ready', false));
});

test('speech_ready shared prop is true when binary and model exist', function () {
    speechModelFile();
    $binary = test()->extrasDir.'/bin/'.(PHP_OS_FAMILY === 'Windows' ? 'win/whisper-cli.exe' : 'mac/whisper-cli');
    File::ensureDirectoryExists(dirname($binary));
    File::put($binary, 'fake-binary');

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('speech_ready', true));
});

test('destroy deletes the model file', function () {
    $path = speechModelFile();

    $this->deleteJson(route('speech.model.destroy'))
        ->assertOk()
        ->assertJson(['state' => 'missing']);

    expect(File::exists($path))->toBeFalse();
});
