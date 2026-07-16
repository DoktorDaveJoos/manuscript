<?php

use App\Ai\Agents\BookChatAgent;
use App\Ai\Agents\ProseReviser;
use App\Enums\AiProvider;
use App\Enums\VersionSource;
use App\Enums\VersionStatus;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Character;
use App\Models\EditorialReview;
use App\Models\EditorialReviewSection;
use App\Models\License;
use App\Models\Scene;
use App\Models\Storyline;
use App\Models\WikiEntry;
use App\Services\Normalization\NormalizationService;

beforeEach(function () {
    License::factory()->create();
});

test('revise rejects a stale expected_current_version_id with 409', function () {
    ProseReviser::fake(['<p>Whatever.</p>']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $current = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>Original.</p>',
        'status' => VersionStatus::Accepted,
    ]);

    $this->postJson(
        route('chapters.ai.revise', [$book, $chapter]),
        ['expected_current_version_id' => $current->id + 9999],
    )->assertStatus(409);
});

test('revise requires expected_current_version_id', function () {
    ProseReviser::fake(['<p>Whatever.</p>']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>Original.</p>',
        'status' => VersionStatus::Accepted,
    ]);

    $this->postJson(
        route('chapters.ai.revise', [$book, $chapter]),
        [],
    )->assertStatus(422)
        ->assertJsonValidationErrors('expected_current_version_id');
});

test('reviseScene requires expected_current_version_id', function () {
    ProseReviser::fake(['<p>Whatever.</p>']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $scene = Scene::factory()->for($chapter)->create([
        'content' => '<p>Scene prose.</p>',
        'sort_order' => 0,
    ]);

    $this->postJson(
        route('chapters.scenes.ai.revise', [$book, $chapter, $scene]),
        [],
    )->assertStatus(422)
        ->assertJsonValidationErrors('expected_current_version_id');
});

test('revise-editorial requires expected_current_version_id', function () {
    ProseReviser::fake(['<p>Whatever.</p>']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>Original.</p>',
        'status' => VersionStatus::Accepted,
    ]);

    $review = EditorialReview::factory()->for($book)->create([
        'status' => 'completed',
        'completed_at' => now(),
    ]);
    $review->chapterNotes()->create([
        'chapter_id' => $chapter->id,
        'notes' => ['chapter_note' => 'Tighten the first scene.'],
    ]);

    $this->postJson(
        route('chapters.ai.reviseEditorial', [$book, $chapter]),
        [],
    )->assertStatus(422)
        ->assertJsonValidationErrors('expected_current_version_id');
});

test('revise streams prose revision and auto-applies the new version', function () {
    ProseReviser::fake(['<p>The revised prose text.</p>']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $original = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>Original prose text.</p>',
        'status' => VersionStatus::Accepted,
    ]);

    $response = $this->post(
        route('chapters.ai.revise', [$book, $chapter]),
        ['expected_current_version_id' => $original->id],
    );
    $response->assertOk();
    $response->streamedContent(); // drains the stream so `then()` fires

    ProseReviser::assertPrompted(fn ($prompt) => true);

    // The new revision is auto-applied: it becomes the current/accepted version.
    $newVersion = $chapter->versions()->orderByDesc('version_number')->first();
    expect($newVersion->version_number)->toBe(2);
    expect($newVersion->is_current)->toBeTrue();
    expect($newVersion->status)->toBe(VersionStatus::Accepted);
    expect($newVersion->source)->toBe(VersionSource::AiRevision);
    expect($newVersion->content)->toContain('revised prose');

    // The previous version is no longer current.
    expect($original->fresh()->is_current)->toBeFalse();

    // Scenes reflect the new content (so the editor renders the revision).
    expect($chapter->fresh()->scenes()->first()->content)->toContain('revised prose');
});

test('revise preserves the scene title when the AI returns a single segment', function () {
    ProseReviser::fake(['<p>Polished prose.</p>']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create([
        'title' => 'The morning',
        'content' => '<p>Original prose.</p>',
        'sort_order' => 0,
    ]);
    $current = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>Original prose.</p>',
        'status' => VersionStatus::Accepted,
    ]);

    $response = $this->post(
        route('chapters.ai.revise', [$book, $chapter]),
        ['expected_current_version_id' => $current->id],
    );
    $response->assertOk();
    $response->streamedContent();

    $scene = $chapter->fresh()->scenes()->first();
    expect($scene->title)->toBe('The morning');
    expect($scene->content)->toContain('Polished prose');
});

test('revise-editorial streams a rewrite addressing editorial feedback and applies the new version', function () {
    ProseReviser::fake(['<p>Rewritten with feedback.</p>']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $current = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>Original prose text.</p>',
        'status' => VersionStatus::Accepted,
    ]);

    $review = EditorialReview::factory()->for($book)->create([
        'status' => 'completed',
        'completed_at' => now(),
    ]);
    $review->chapterNotes()->create([
        'chapter_id' => $chapter->id,
        'notes' => ['chapter_note' => 'The opening drags; tighten the first scene.'],
    ]);
    EditorialReviewSection::factory()->for($review)->create([
        'type' => 'pacing',
        'findings' => [[
            'key' => EditorialReviewSection::findingKey('pacing', 'Mid-chapter sag.'),
            'severity' => 'warning',
            'description' => 'Mid-chapter sag.',
            'chapter_references' => [$chapter->id],
            'recommendation' => 'Cut the second flashback.',
        ]],
    ]);

    $response = $this->post(
        route('chapters.ai.reviseEditorial', [$book, $chapter]),
        ['expected_current_version_id' => $current->id],
    );
    $response->assertOk();
    $response->streamedContent();

    $newVersion = $chapter->versions()->orderByDesc('version_number')->first();
    expect($newVersion->is_current)->toBeTrue()
        ->and($newVersion->source)->toBe(VersionSource::EditorialRewrite)
        ->and($newVersion->content)->toContain('Rewritten with feedback');
});

test('revise-editorial returns 422 when the book has no completed review', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => '<p>Original prose text.</p>',
    ]);

    EditorialReview::factory()->for($book)->create(['status' => 'analyzing']);

    $this->postJson(route('chapters.ai.reviseEditorial', [$book, $chapter]))
        ->assertUnprocessable();
});

test('revise-editorial returns 422 when the review has no feedback for this chapter', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $other = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => '<p>Original prose text.</p>',
    ]);

    $review = EditorialReview::factory()->for($book)->create([
        'status' => 'completed',
        'completed_at' => now(),
    ]);
    $review->chapterNotes()->create([
        'chapter_id' => $other->id,
        'notes' => ['chapter_note' => 'Notes for a different chapter.'],
    ]);

    $this->postJson(route('chapters.ai.reviseEditorial', [$book, $chapter]))
        ->assertUnprocessable();
});

test('revise-editorial ignores findings the user marked as resolved', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => '<p>Original prose text.</p>',
    ]);

    $key = EditorialReviewSection::findingKey('pacing', 'Mid-chapter sag.');
    $review = EditorialReview::factory()->for($book)->create([
        'status' => 'completed',
        'completed_at' => now(),
        'resolved_findings' => [$key],
    ]);
    EditorialReviewSection::factory()->for($review)->create([
        'type' => 'pacing',
        'findings' => [[
            'key' => $key,
            'severity' => 'warning',
            'description' => 'Mid-chapter sag.',
            'chapter_references' => [$chapter->id],
            'recommendation' => 'Cut the second flashback.',
        ]],
    ]);

    // The only finding is resolved and there is no chapter note — nothing to address.
    $this->postJson(route('chapters.ai.reviseEditorial', [$book, $chapter]))
        ->assertUnprocessable();
});

test('prose reviser instructions include the editorial directive when given', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $agent = new ProseReviser($book, $chapter, "Chapter note from the editor:\nTighten the first scene.");

    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('EDITORIAL FEEDBACK')
        ->and($instructions)->toContain('Tighten the first scene.');
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

test('reviseScene revises only the targeted scene and snapshots the chapter', function () {
    ProseReviser::fake(['<p>Polished scene two.</p>']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 6]);

    $current = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>Scene one prose.</p><hr><p>Scene two prose.</p>',
        'status' => VersionStatus::Accepted,
    ]);

    $sceneOne = Scene::factory()->for($chapter)->create([
        'sort_order' => 0,
        'content' => '<p>Scene one prose.</p>',
        'word_count' => 3,
    ]);
    $sceneTwo = Scene::factory()->for($chapter)->create([
        'sort_order' => 1,
        'content' => '<p>Scene two prose.</p>',
        'word_count' => 3,
        'content_version' => 6,
    ]);

    $response = $this->post(
        route('chapters.scenes.ai.revise', [$book, $chapter, $sceneTwo]),
        ['expected_current_version_id' => $current->id],
    );
    $response->assertOk();
    $response->streamedContent();

    ProseReviser::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Scene two prose')
        && ! str_contains($prompt->prompt, 'Scene one prose'));

    expect($sceneOne->fresh()->content)->toBe('<p>Scene one prose.</p>')
        ->and($sceneTwo->fresh()->content)->toContain('Polished scene two')
        ->and($sceneTwo->fresh()->content_version)->toBe(7)
        ->and($sceneTwo->fresh()->word_count)->toBe(3)
        ->and($chapter->fresh()->word_count)->toBe(6);

    $newVersion = $chapter->versions()->orderByDesc('version_number')->first();
    expect($newVersion->version_number)->toBe(2);
    expect($newVersion->is_current)->toBeTrue();
    expect($newVersion->source)->toBe(VersionSource::AiRevision);
    expect($newVersion->content)->toContain('Scene one prose');
    expect($newVersion->content)->toContain('Polished scene two');
});

test('reviseScene 404s when scene does not belong to chapter', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $otherChapter = Chapter::factory()->for($book)->for($storyline)->create();
    $foreignScene = Scene::factory()->for($otherChapter)->create();

    $this->post(route('chapters.scenes.ai.revise', [$book, $chapter, $foreignScene]))
        ->assertNotFound();
});

test('reviseScene fails when the scene has no content', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $scene = Scene::factory()->for($chapter)->create(['content' => '']);

    $this->postJson(route('chapters.scenes.ai.revise', [$book, $chapter, $scene]))
        ->assertStatus(422);
});

test('revise fails when chapter has no content', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $current = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => null,
    ]);

    $this->post(
        route('chapters.ai.revise', [$book, $chapter]),
        ['expected_current_version_id' => $current->id],
    )->assertStatus(422);
});

test('revise fails without api key', function () {
    $book = Book::factory()->create();
    AiSetting::factory()->withoutKey()->create([
        'provider' => AiProvider::Anthropic,
        'enabled' => true,
    ]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $current = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => 'Some content.',
    ]);

    $this->post(
        route('chapters.ai.revise', [$book, $chapter]),
        ['expected_current_version_id' => $current->id],
    )->assertStatus(422);
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

    $current = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => $longContent,
    ]);

    $this->post(
        route('chapters.ai.revise', [$book, $chapter]),
        ['expected_current_version_id' => $current->id],
    )->assertStatus(422);
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

    $this->post(
        route('chapters.ai.revise', [$book, $chapter]),
        ['expected_current_version_id' => $version->id],
    );

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

    $current = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => 'Original.',
    ]);

    $this->post(
        route('chapters.ai.revise', [$book, $chapter]),
        ['expected_current_version_id' => $current->id],
    );

    ProseReviser::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, '<hr>'));
});

test('chat streams response from BookChatAgent', function () {
    BookChatAgent::fake(['This is the AI response about the book.']);

    $book = Book::factory()->withAi()->create();

    $response = $this->post(route('books.ai.chat', $book), [
        'message' => 'What happens in chapter 1?',
    ]);
    $response->assertOk();

    BookChatAgent::assertPrompted(fn ($prompt) => true);
});

test('chat validates message is required', function () {
    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.ai.chat', $book), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

test('chat fails without AI configured', function () {
    $book = Book::factory()->create();
    AiSetting::factory()->withoutKey()->create([
        'provider' => AiProvider::Anthropic,
        'enabled' => true,
    ]);

    $this->postJson(route('books.ai.chat', $book), ['message' => 'Hello'])
        ->assertStatus(422);
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
