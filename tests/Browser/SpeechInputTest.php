<?php

use App\Models\Book;
use App\Services\Speech\SpeechModelManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->modelDir = sys_get_temp_dir().'/manuscript-speech-browser-models-'.uniqid();
    $this->extrasDir = sys_get_temp_dir().'/manuscript-speech-browser-extras-'.uniqid();
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

function installFakeSpeechSetup(): void
{
    $manager = app(SpeechModelManager::class);
    File::ensureDirectoryExists($manager->modelDir());
    File::put($manager->path(), 'fake-model');

    $binary = test()->extrasDir.'/bin/'.(PHP_OS_FAMILY === 'Windows' ? 'win/whisper-cli.exe' : 'mac/whisper-cli');
    File::ensureDirectoryExists(dirname($binary));
    File::put($binary, 'fake-binary');
}

it('offers the speech model download in settings when nothing is installed', function () {
    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Speech input')
        ->assertSee('Download model');
});

it('shows the installed state with a remove action once the model exists', function () {
    installFakeSpeechSetup();

    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Speech input')
        ->assertSee('Model installed')
        ->assertSee('Remove model');
});

it('shows the mic button in the AI chat input once speech is ready', function () {
    installFakeSpeechSetup();
    $book = Book::factory()->withAi()->create();

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors()
        ->assertPresent('button[aria-label="Start voice input"]');
});

it('hides the mic button while no speech model is installed', function () {
    $book = Book::factory()->withAi()->create();

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors()
        ->assertMissing('button[aria-label="Start voice input"]');
});
