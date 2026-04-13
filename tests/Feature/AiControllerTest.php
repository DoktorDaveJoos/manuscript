<?php

use App\Ai\Agents\BookChatAgent;
use App\Ai\Agents\NextChapterAdvisor;
use App\Ai\Agents\ProseReviser;
use App\Ai\Agents\TextBeautifier;
use App\Enums\AiProvider;
use App\Jobs\ExtractEntitiesJob;
use App\Jobs\GenerateEmbeddingsJob;
use App\Jobs\RunAnalysisJob;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Character;
use App\Models\License;
use App\Models\Scene;
use App\Models\Storyline;
use App\Models\WikiEntry;
use App\Services\Normalization\NormalizationService;
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

test('extract characters dispatches ExtractEntitiesJob', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(route('books.ai.extractCharacters', [$book, $chapter]))
        ->assertOk()
        ->assertJsonPath('message', 'Entity extraction started.');

    Queue::assertPushed(ExtractEntitiesJob::class);
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

test('prose reviser instructions include character, entity, and narrative context', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 2,
        'title' => 'The Arrival',
        'summary' => 'Elena arrives in Ravenholm.',
    ]);

    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 3]);

    $character = Character::factory()->for($book)->create([
        'name' => 'Elena Vasquez',
        'description' => 'A seasoned detective.',
    ]);
    $chapter->characters()->attach($character, ['role' => 'protagonist']);

    $wikiEntry = WikiEntry::factory()->for($book)->location()->create([
        'name' => 'Ravenholm',
        'description' => 'A fog-shrouded coastal town.',
    ]);
    $chapter->wikiEntries()->attach($wikiEntry);

    $reviser = new ProseReviser($book, $chapter);
    $instructions = $reviser->instructions();

    expect($instructions)
        ->toContain('Elena Vasquez')
        ->toContain('protagonist')
        ->toContain('Ravenholm')
        ->toContain('Locations')
        ->toContain('Narrative Position')
        ->toContain('The Arrival');
});

test('prose reviser instructions work without context', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $reviser = new ProseReviser($book, $chapter);
    $instructions = $reviser->instructions();

    expect($instructions)
        ->toContain('expert prose editor')
        ->not->toContain('MANUSCRIPT CONTEXT');
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

test('revise rejects chapter over 12000 words', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    // Create a scene with enough words to exceed the limit
    $longContent = '<p>'.implode(' ', array_fill(0, 13000, 'word')).'</p>';
    Scene::factory()->for($chapter)->create([
        'content' => $longContent,
        'sort_order' => 0,
    ]);

    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => $longContent,
    ]);

    $this->post(route('chapters.ai.revise', [$book, $chapter]))
        ->assertStatus(422);
});

test('AI revision content is normalized before saving', function () {
    $raw = '<p>She walked&nbsp;slowly&mdash;then stopped&hellip; &ldquo;Hello,&rdquo; she said.</p>';

    // Simulate the normalization pipeline used in streamAgentRevision
    $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $result = app(NormalizationService::class)->normalize($decoded, 'en');

    $content = $result['content'];

    expect($content)->not->toContain('&nbsp;');
    expect($content)->not->toContain('&mdash;');
    expect($content)->not->toContain('&hellip;');
    expect($content)->not->toContain('&ldquo;');
    expect($content)->not->toContain('&rdquo;');
    // Should contain actual Unicode characters
    expect($content)->toContain("\u{2014}"); // em dash
    expect($content)->toContain("\u{2026}"); // ellipsis
});

test('revise syncs currentVersion content from scenes before processing', function () {
    ProseReviser::fake(['The revised prose text.']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $version = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>Stale content from ages ago.</p>',
    ]);

    // Simulate user editing scenes without snapshotting
    Scene::factory()->for($chapter)->create([
        'content' => '<p>Fresh scene content.</p>',
        'sort_order' => 0,
    ]);

    $this->post(route('chapters.ai.revise', [$book, $chapter]));

    $version->refresh();
    expect($version->content)->toBe('<p>Fresh scene content.</p>');
});

test('revise uses scene breaks in prompt', function () {
    ProseReviser::fake(['The revised prose text.']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    Scene::factory()->for($chapter)->create([
        'content' => '<p>Scene one</p>',
        'sort_order' => 0,
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>Scene two</p>',
        'sort_order' => 1,
    ]);

    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => 'Original.',
    ]);

    $this->post(route('chapters.ai.revise', [$book, $chapter]));

    ProseReviser::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, '<hr>'));
});

test('chat accepts chapter_id and conversation_id', function () {
    BookChatAgent::fake(['AI response with context.']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->post(route('books.ai.chat', $book), [
        'message' => 'What happens in this chapter?',
        'chapter_id' => $chapter->id,
    ])->assertOk();

    BookChatAgent::assertPrompted(fn ($prompt) => true);
});

test('chat works without optional params', function () {
    BookChatAgent::fake(['AI response.']);

    $book = Book::factory()->withAi()->create();

    $this->post(route('books.ai.chat', $book), [
        'message' => 'Tell me about the plot.',
    ])->assertOk();

    BookChatAgent::assertPrompted(fn ($prompt) => true);
});

test('chat rejects invalid conversation_id format', function () {
    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.ai.chat', $book), [
        'message' => 'Hello',
        'conversation_id' => str_repeat('x', 37),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('conversation_id');
});

test('chat rejects cross-book chapter_id', function () {
    BookChatAgent::fake(['Response.']);

    $book = Book::factory()->withAi()->create();
    $otherBook = Book::factory()->create();
    $storyline = Storyline::factory()->for($otherBook)->create();
    $otherChapter = Chapter::factory()->for($otherBook)->for($storyline)->create();

    $this->postJson(route('books.ai.chat', $book), [
        'message' => 'Hello',
        'chapter_id' => $otherChapter->id,
    ])->assertNotFound();
});
