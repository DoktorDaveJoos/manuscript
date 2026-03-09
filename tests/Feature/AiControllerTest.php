<?php

use App\Ai\Agents\NextChapterAdvisor;
use App\Ai\Agents\ProseReviser;
use App\Ai\Agents\TextBeautifier;
use App\Enums\AiProvider;
use App\Jobs\ExtractCharactersJob;
use App\Jobs\GenerateEmbeddingsJob;
use App\Jobs\RunAnalysisJob;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\License;
use App\Models\Storyline;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    License::factory()->create();
});

test('analyze dispatches RunAnalysisJob', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(route('books.ai.analyze', $book), [
        'type' => 'pacing',
    ])->assertOk()
        ->assertJsonPath('message', 'Analysis started.');

    Queue::assertPushed(RunAnalysisJob::class, function ($job) {
        return true;
    });
});

test('analyze validates type is required', function () {
    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.ai.analyze', $book), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');
});

test('analyze validates type is a valid analysis type', function () {
    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.ai.analyze', $book), [
        'type' => 'invalid_type',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('type');
});

test('extract characters dispatches ExtractCharactersJob', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(route('books.ai.extractCharacters', [$book, $chapter]))
        ->assertOk()
        ->assertJsonPath('message', 'Character extraction started.');

    Queue::assertPushed(ExtractCharactersJob::class);
});

test('embed dispatches GenerateEmbeddingsJob for each chapter', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter1 = Chapter::factory()->for($book)->for($storyline)->create();
    $chapter2 = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter1)->create(['is_current' => true]);
    ChapterVersion::factory()->for($chapter2)->create(['is_current' => true]);

    $this->postJson(route('books.ai.embed', $book))
        ->assertOk()
        ->assertJsonPath('message', '2 embedding jobs dispatched.');

    Queue::assertPushed(GenerateEmbeddingsJob::class, 2);
});

test('next chapter returns structured suggestion', function () {
    NextChapterAdvisor::fake();

    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.ai.nextChapter', $book))
        ->assertOk()
        ->assertJsonStructure(['suggestion', 'open_plot_points', 'neglected_characters', 'hook_ideas']);
});

test('revise streams prose revision and creates new version', function () {
    ProseReviser::fake(['The revised prose text.']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => 'Original prose text.',
    ]);

    $response = $this->post(route('chapters.ai.revise', [$book, $chapter]));
    $response->assertOk();

    ProseReviser::assertPrompted(fn ($prompt) => true);
});

test('revise fails when chapter has no content', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => null,
    ]);

    $this->post(route('chapters.ai.revise', [$book, $chapter]))
        ->assertStatus(422);
});

test('next chapter fails without api key', function () {
    $book = Book::factory()->create();
    AiSetting::factory()->withoutKey()->create([
        'provider' => AiProvider::Anthropic,
        'enabled' => true,
    ]);

    $this->postJson(route('books.ai.nextChapter', $book))
        ->assertStatus(422);
});

test('beautify streams and creates new version', function () {
    TextBeautifier::fake(['The beautified text.']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => 'Original text to beautify.',
    ]);

    $response = $this->post(route('chapters.ai.beautify', [$book, $chapter]));
    $response->assertOk();

    TextBeautifier::assertPrompted(fn ($prompt) => true);
});

test('beautify fails when chapter has no content', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => null,
    ]);

    $this->post(route('chapters.ai.beautify', [$book, $chapter]))
        ->assertStatus(422);
});

test('beautify fails without api key', function () {
    $book = Book::factory()->create();
    AiSetting::factory()->withoutKey()->create([
        'provider' => AiProvider::Anthropic,
        'enabled' => true,
    ]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => 'Some content.',
    ]);

    $this->post(route('chapters.ai.beautify', [$book, $chapter]))
        ->assertStatus(422);
});
