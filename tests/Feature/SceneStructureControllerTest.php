<?php

use App\Ai\Agents\SceneStructurer;
use App\Enums\AiProvider;
use App\Enums\VersionSource;
use App\Enums\VersionStatus;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\License;
use App\Models\Scene;
use App\Models\Storyline;

beforeEach(function () {
    License::factory()->create();
});

function chapterWithSingleScene(string $content): array
{
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    Scene::factory()->for($chapter)->create([
        'title' => 'Scene 1',
        'content' => $content,
        'sort_order' => 0,
    ]);
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => $content,
        'status' => VersionStatus::Accepted,
    ]);

    return [$book, $chapter];
}

function fourParagraphChapter(): string
{
    return '<p>Rain hammered the station roof as Elena stepped off the train.</p>'
        .'<p>She found the platform empty and the ticket office dark.</p>'
        .'<p>Across town, Marlowe poured a second drink and watched the door.</p>'
        .'<p>He had been waiting ten years for this night to arrive.</p>';
}

test('suggest returns the proposed scenes with word counts, excerpts, and a content hash', function () {
    SceneStructurer::fake(fn () => ['scenes' => [
        ['title' => 'The Arrival', 'start_paragraph' => 0],
        ['title' => 'Old Debts', 'start_paragraph' => 2],
    ]]);

    [$book, $chapter] = chapterWithSingleScene(fourParagraphChapter());

    $response = $this->postJson(route('chapters.ai.structureScenes', [$book, $chapter]))
        ->assertSuccessful();

    $response->assertJsonPath('scenes.0.title', 'The Arrival')
        ->assertJsonPath('scenes.0.start_paragraph', 0)
        ->assertJsonPath('scenes.1.title', 'Old Debts')
        ->assertJsonPath('scenes.1.start_paragraph', 2)
        ->assertJsonPath('paragraph_count', 4)
        ->assertJsonPath('current_scene_count', 1);

    expect($response->json('scenes.0.word_count'))->toBeGreaterThan(0)
        ->and($response->json('scenes.0.excerpt'))->toContain('Rain hammered')
        ->and($response->json('scenes.1.excerpt'))->toContain('Marlowe')
        ->and($response->json('content_hash'))->toBeString()->not->toBeEmpty();
});

test('suggest prompts the agent with numbered plain-text paragraphs', function () {
    SceneStructurer::fake(fn () => ['scenes' => [
        ['title' => 'All of it', 'start_paragraph' => 0],
    ]]);

    [$book, $chapter] = chapterWithSingleScene(fourParagraphChapter());

    $this->postJson(route('chapters.ai.structureScenes', [$book, $chapter]))
        ->assertSuccessful();

    SceneStructurer::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, '[0] Rain hammered')
        && str_contains($prompt->prompt, '[3] He had been waiting')
        && ! str_contains($prompt->prompt, '<p>'));
});

test('suggest normalizes agent output: sorts scenes, drops out-of-range indices, and anchors the first scene at zero', function () {
    SceneStructurer::fake(fn () => ['scenes' => [
        ['title' => 'Ghost', 'start_paragraph' => 99],
        ['title' => 'Late Half', 'start_paragraph' => 2],
        ['title' => 'Opening', 'start_paragraph' => 1],
    ]]);

    [$book, $chapter] = chapterWithSingleScene(fourParagraphChapter());

    $response = $this->postJson(route('chapters.ai.structureScenes', [$book, $chapter]))
        ->assertSuccessful();

    expect($response->json('scenes'))->toHaveCount(2);
    $response->assertJsonPath('scenes.0.title', 'Opening')
        ->assertJsonPath('scenes.0.start_paragraph', 0)
        ->assertJsonPath('scenes.1.title', 'Late Half')
        ->assertJsonPath('scenes.1.start_paragraph', 2);
});

test('suggest ignores existing scene breaks when restructuring a multi-scene chapter', function () {
    SceneStructurer::fake(fn () => ['scenes' => [
        ['title' => 'One Scene', 'start_paragraph' => 0],
    ]]);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create([
        'content' => '<p>First scene prose.</p>',
        'sort_order' => 0,
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>Second scene prose.</p>',
        'sort_order' => 1,
    ]);
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>First scene prose.</p><hr><p>Second scene prose.</p>',
    ]);

    $response = $this->postJson(route('chapters.ai.structureScenes', [$book, $chapter]))
        ->assertSuccessful();

    $response->assertJsonPath('paragraph_count', 2)
        ->assertJsonPath('current_scene_count', 2);

    SceneStructurer::assertPrompted(fn ($prompt) => ! str_contains($prompt->prompt, '<hr>'));
});

test('suggest fails when the chapter has no content', function () {
    [$book, $chapter] = chapterWithSingleScene(fourParagraphChapter());
    $chapter->scenes()->forceDelete();

    $this->postJson(route('chapters.ai.structureScenes', [$book, $chapter]))
        ->assertUnprocessable();
});

test('suggest fails without a configured AI provider', function () {
    $book = Book::factory()->create();
    AiSetting::factory()->withoutKey()->create([
        'provider' => AiProvider::Anthropic,
        'enabled' => true,
    ]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(route('chapters.ai.structureScenes', [$book, $chapter]))
        ->assertUnprocessable();
});

test('suggest rejects chapters over 12000 words', function () {
    $longContent = '<p>'.implode(' ', array_fill(0, 13000, 'word')).'</p>';
    [$book, $chapter] = chapterWithSingleScene($longContent);

    $this->postJson(route('chapters.ai.structureScenes', [$book, $chapter]))
        ->assertUnprocessable();
});

test('suggest 404s for a chapter of another book', function () {
    [, $chapter] = chapterWithSingleScene(fourParagraphChapter());
    $otherBook = Book::factory()->create();

    $this->postJson(route('chapters.ai.structureScenes', [$otherBook, $chapter]))
        ->assertNotFound();
});

test('apply splits the chapter into the accepted scenes and creates a new version', function () {
    $content = fourParagraphChapter();
    [$book, $chapter] = chapterWithSingleScene($content);
    $original = $chapter->currentVersion;

    $this->postJson(route('chapters.ai.structureScenes.apply', [$book, $chapter]), [
        'content_hash' => hash('xxh128', $content),
        'scenes' => [
            ['title' => 'The Arrival', 'start_paragraph' => 0],
            ['title' => 'Old Debts', 'start_paragraph' => 2],
        ],
    ])->assertSuccessful();

    $chapter->refresh()->load('scenes');

    expect($chapter->scenes)->toHaveCount(2);

    $first = $chapter->scenes[0];
    expect($first->title)->toBe('The Arrival')
        ->and($first->content)->toContain('Rain hammered')
        ->and($first->content)->toContain('platform empty')
        ->and($first->content)->not->toContain('Marlowe');

    $second = $chapter->scenes[1];
    expect($second->title)->toBe('Old Debts')
        ->and($second->content)->toContain('Marlowe')
        ->and($second->content)->toContain('waiting ten years')
        ->and($second->content)->not->toContain('Rain hammered');

    $newVersion = $chapter->versions()->orderByDesc('version_number')->first();
    expect($newVersion->version_number)->toBe(2)
        ->and($newVersion->is_current)->toBeTrue()
        ->and($newVersion->status)->toBe(VersionStatus::Accepted)
        ->and($newVersion->source)->toBe(VersionSource::SceneStructure)
        ->and($newVersion->content)->toContain('<hr>')
        ->and($newVersion->scene_map)->toBe([
            ['title' => 'The Arrival', 'sort_order' => 0],
            ['title' => 'Old Debts', 'sort_order' => 1],
        ]);

    expect($original->fresh()->is_current)->toBeFalse();
});

test('apply preserves the prose byte-identically across the split', function () {
    $content = fourParagraphChapter();
    [$book, $chapter] = chapterWithSingleScene($content);

    $this->postJson(route('chapters.ai.structureScenes.apply', [$book, $chapter]), [
        'content_hash' => hash('xxh128', $content),
        'scenes' => [
            ['title' => 'A', 'start_paragraph' => 0],
            ['title' => 'B', 'start_paragraph' => 2],
        ],
    ])->assertSuccessful();

    $chapter->refresh()->load('scenes');

    expect(str_replace('<hr>', '', $chapter->scenes->pluck('content')->implode('<hr>')))
        ->toBe($content);
});

test('apply replaces an existing multi-scene structure', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create([
        'content' => '<p>First scene prose.</p>',
        'sort_order' => 0,
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>Second scene prose.</p>',
        'sort_order' => 1,
    ]);
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>First scene prose.</p><hr><p>Second scene prose.</p>',
    ]);

    $fullContent = "<p>First scene prose.</p>\n<p>Second scene prose.</p>";

    $this->postJson(route('chapters.ai.structureScenes.apply', [$book, $chapter]), [
        'content_hash' => hash('xxh128', $fullContent),
        'scenes' => [
            ['title' => 'Everything', 'start_paragraph' => 0],
        ],
    ])->assertSuccessful();

    $chapter->refresh()->load('scenes');

    expect($chapter->scenes)->toHaveCount(1)
        ->and($chapter->scenes[0]->title)->toBe('Everything')
        ->and($chapter->scenes[0]->content)->toContain('First scene prose')
        ->and($chapter->scenes[0]->content)->toContain('Second scene prose');
});

test('apply updates scene and chapter word counts', function () {
    $content = fourParagraphChapter();
    [$book, $chapter] = chapterWithSingleScene($content);

    $this->postJson(route('chapters.ai.structureScenes.apply', [$book, $chapter]), [
        'content_hash' => hash('xxh128', $content),
        'scenes' => [
            ['title' => 'A', 'start_paragraph' => 0],
            ['title' => 'B', 'start_paragraph' => 2],
        ],
    ])->assertSuccessful();

    $chapter->refresh()->load('scenes');

    expect($chapter->scenes[0]->word_count)->toBeGreaterThan(0)
        ->and($chapter->scenes[1]->word_count)->toBeGreaterThan(0)
        ->and($chapter->word_count)->toBe(
            $chapter->scenes[0]->word_count + $chapter->scenes[1]->word_count,
        );
});

test('apply rejects a stale content hash with 409', function () {
    [$book, $chapter] = chapterWithSingleScene(fourParagraphChapter());

    $this->postJson(route('chapters.ai.structureScenes.apply', [$book, $chapter]), [
        'content_hash' => 'stale-hash',
        'scenes' => [
            ['title' => 'A', 'start_paragraph' => 0],
        ],
    ])->assertConflict();

    expect($chapter->refresh()->load('scenes')->scenes)->toHaveCount(1);
});

test('apply rejects boundaries that do not start at paragraph zero', function () {
    $content = fourParagraphChapter();
    [$book, $chapter] = chapterWithSingleScene($content);

    $this->postJson(route('chapters.ai.structureScenes.apply', [$book, $chapter]), [
        'content_hash' => hash('xxh128', $content),
        'scenes' => [
            ['title' => 'A', 'start_paragraph' => 1],
        ],
    ])->assertUnprocessable();
});

test('apply rejects non-increasing boundaries', function () {
    $content = fourParagraphChapter();
    [$book, $chapter] = chapterWithSingleScene($content);

    $this->postJson(route('chapters.ai.structureScenes.apply', [$book, $chapter]), [
        'content_hash' => hash('xxh128', $content),
        'scenes' => [
            ['title' => 'A', 'start_paragraph' => 0],
            ['title' => 'B', 'start_paragraph' => 2],
            ['title' => 'C', 'start_paragraph' => 2],
        ],
    ])->assertUnprocessable();
});

test('apply rejects boundaries beyond the paragraph count', function () {
    $content = fourParagraphChapter();
    [$book, $chapter] = chapterWithSingleScene($content);

    $this->postJson(route('chapters.ai.structureScenes.apply', [$book, $chapter]), [
        'content_hash' => hash('xxh128', $content),
        'scenes' => [
            ['title' => 'A', 'start_paragraph' => 0],
            ['title' => 'B', 'start_paragraph' => 4],
        ],
    ])->assertUnprocessable();
});

test('apply validates the request payload', function (array $payload) {
    [$book, $chapter] = chapterWithSingleScene(fourParagraphChapter());

    $this->postJson(route('chapters.ai.structureScenes.apply', [$book, $chapter]), $payload)
        ->assertUnprocessable();
})->with([
    'missing scenes' => [['content_hash' => 'x']],
    'empty scenes' => [['content_hash' => 'x', 'scenes' => []]],
    'missing hash' => [['scenes' => [['title' => 'A', 'start_paragraph' => 0]]]],
    'missing title' => [['content_hash' => 'x', 'scenes' => [['start_paragraph' => 0]]]],
    'negative start' => [['content_hash' => 'x', 'scenes' => [['title' => 'A', 'start_paragraph' => -1]]]],
]);

test('apply 404s for a chapter of another book', function () {
    [, $chapter] = chapterWithSingleScene(fourParagraphChapter());
    $otherBook = Book::factory()->create();

    $this->postJson(route('chapters.ai.structureScenes.apply', [$otherBook, $chapter]), [
        'content_hash' => 'x',
        'scenes' => [['title' => 'A', 'start_paragraph' => 0]],
    ])->assertNotFound();
});
