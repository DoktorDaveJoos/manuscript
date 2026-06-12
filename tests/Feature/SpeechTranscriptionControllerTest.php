<?php

use App\Services\Speech\SpeechModelManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    $this->modelDir = sys_get_temp_dir().'/manuscript-speech-tx-models-'.uniqid();
    $this->extrasDir = sys_get_temp_dir().'/manuscript-speech-tx-extras-'.uniqid();
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

function fakeWhisperBinary(): void
{
    $binary = test()->extrasDir.'/bin/'.(PHP_OS_FAMILY === 'Windows' ? 'win/whisper-cli.exe' : 'mac/whisper-cli');
    File::ensureDirectoryExists(dirname($binary));
    File::put($binary, 'fake-binary');
}

function fakeSpeechModel(): void
{
    $manager = app(SpeechModelManager::class);
    File::ensureDirectoryExists($manager->modelDir());
    File::put($manager->path(), 'fake-model');
}

test('transcribes an uploaded recording', function () {
    fakeWhisperBinary();
    fakeSpeechModel();
    Process::fake(['*' => Process::result("  Es war einmal ein Drache. \n")]);

    $this->post(route('speech.transcriptions.store'), [
        'audio' => UploadedFile::fake()->create('recording.wav', 256, 'audio/wav'),
    ])
        ->assertOk()
        ->assertJson(['text' => 'Es war einmal ein Drache.']);

    Process::assertRan(fn ($process) => str_contains($process->command[0] ?? '', 'whisper-cli')
        && in_array('-l', $process->command, true)
        && in_array('auto', $process->command, true));
});

test('rejects transcription while the binary is missing', function () {
    fakeSpeechModel();

    $this->post(route('speech.transcriptions.store'), [
        'audio' => UploadedFile::fake()->create('recording.wav', 256, 'audio/wav'),
    ])->assertServiceUnavailable();
});

test('rejects transcription while the model is missing', function () {
    fakeWhisperBinary();

    $this->post(route('speech.transcriptions.store'), [
        'audio' => UploadedFile::fake()->create('recording.wav', 256, 'audio/wav'),
    ])->assertConflict();
});

test('requires an audio upload', function () {
    fakeWhisperBinary();
    fakeSpeechModel();

    $this->postJson(route('speech.transcriptions.store'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['audio']);
});

test('surfaces a transcriber failure as an error response', function () {
    fakeWhisperBinary();
    fakeSpeechModel();
    Process::fake(['*' => Process::result(output: '', errorOutput: 'failed to load model', exitCode: 1)]);

    $this->post(route('speech.transcriptions.store'), [
        'audio' => UploadedFile::fake()->create('recording.wav', 256, 'audio/wav'),
    ])->assertStatus(500);
});
