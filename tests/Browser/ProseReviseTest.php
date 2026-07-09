<?php

use App\Ai\Agents\ProseReviser;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\License;
use App\Models\Scene;
use App\Models\Storyline;

beforeEach(fn () => License::factory()->create());

function createReviseFixture(): array
{
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 1,
        'title' => 'Chapter 1',
        'word_count' => 2,
    ]);
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'version_number' => 1,
        'content' => '<p>Original prose.</p>',
    ]);
    $scene = Scene::factory()->for($chapter)->create([
        'content' => '<p>Original prose.</p>',
        'sort_order' => 0,
        'word_count' => 2,
    ]);
    $chapter->refreshContentHash();

    return [$book, $chapter, $scene];
}

it('shows the applied toast only when the revision really became the current version', function () {
    // The stream 200s before the agent runs; a mid-stream provider failure
    // leaves no version behind — the UI must not claim success.
    ProseReviser::fake(function () {
        throw new RuntimeException('provider exploded');
    });

    [$book, $chapter] = createReviseFixture();

    $page = visit("/books/{$book->id}/editor?panes={$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="ai"]')
        ->click('[data-testid="run-prose-pass"]')
        ->wait(3);

    $page->assertDontSee('AI revision applied');

    expect($chapter->versions()->count())->toBe(1);
});

it('applies the revision and reports success when the stream completes', function () {
    ProseReviser::fake(['<p>Beautifully revised prose.</p>']);

    [$book, $chapter] = createReviseFixture();

    $page = visit("/books/{$book->id}/editor?panes={$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="ai"]')
        ->click('[data-testid="run-prose-pass"]')
        ->wait(3);

    $page->assertSee('AI revision applied')
        ->assertSee('Beautifully revised prose.')
        ->assertNoJavaScriptErrors();

    expect($chapter->versions()->where('is_current', true)->first()->content)
        ->toContain('Beautifully revised prose');
});
