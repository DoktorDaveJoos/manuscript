<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Scene;
use App\Models\Storyline;
use App\Support\WordCount;

/**
 * @param  list<string>  $sceneContents
 * @return array{0: Book, 1: Chapter, 2: list<Scene>}
 */
function createBookWithSceneContents(array $sceneContents): array
{
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create(['name' => 'Main']);

    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 1,
        'title' => 'Chapter One',
    ]);

    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => implode('', $sceneContents),
    ]);

    $scenes = [];
    foreach ($sceneContents as $i => $content) {
        $scenes[] = Scene::factory()->for($chapter)->create([
            'title' => 'Scene '.($i + 1),
            'content' => $content,
            'sort_order' => $i,
            'word_count' => WordCount::count($content),
        ]);
    }

    $chapter->refreshContentHash();

    return [$book, $chapter, $scenes];
}

const FIND_INPUT = 'input[placeholder="Find in chapter..."]';
const NEXT_MATCH = 'button[title="Next match (Enter)"]';
const PREV_MATCH = 'button[title="Previous match (Shift+Enter)"]';

it('counts a single occurrence as exactly one match', function () {
    [$book, $chapter, $scenes] = createBookWithSceneContents([
        '<p>The caravan crossed the dunes at dawn. A single zephyrion waited beyond the ridge.</p>',
        '<p>Nothing stirred in the valley below. The travelers pressed on in silence.</p>',
    ]);
    $editorSelector = "#scene-{$scenes[0]->id} .ProseMirror";

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click($editorSelector)
        ->keys($editorSelector, 'Control+f')
        ->fill(FIND_INPUT, 'zephyrion')
        ->wait(1)
        ->assertSee('1 of 1')
        ->assertDontSee('1 of 2');
});

it('cycles through matches without snapping back to the first match', function () {
    [$book, $chapter, $scenes] = createBookWithSceneContents([
        '<p>First came the zephyrion of the north. Later a zephyrion of the east appeared.</p>',
        '<p>At last the zephyrion of the south arrived and the circle was complete.</p>',
    ]);
    $editorSelector = "#scene-{$scenes[0]->id} .ProseMirror";

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click($editorSelector)
        ->keys($editorSelector, 'Control+f')
        ->fill(FIND_INPUT, 'zephyrion')
        ->wait(1)
        ->assertSee('1 of 3')
        ->click(NEXT_MATCH)
        ->assertSee('2 of 3')
        // The active match must stay put — it must not silently reset to the
        // first match after a moment (red-green: pins the re-collect loop bug).
        ->wait(1)
        ->assertSee('2 of 3')
        ->click(NEXT_MATCH)
        ->assertSee('3 of 3')
        ->wait(1)
        ->assertSee('3 of 3')
        ->click(NEXT_MATCH)
        ->assertSee('1 of 3')
        ->click(PREV_MATCH)
        ->assertSee('3 of 3')
        ->assertNoJavaScriptErrors();
});

it('keeps focus in the find input when navigating with Enter', function () {
    [$book, $chapter, $scenes] = createBookWithSceneContents([
        '<p>One zephyrion here. Another zephyrion there. A final zephyrion beyond.</p>',
    ]);
    $editorSelector = "#scene-{$scenes[0]->id} .ProseMirror";

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click($editorSelector)
        ->keys($editorSelector, 'Control+f')
        ->fill(FIND_INPUT, 'zephyrion')
        ->wait(1)
        ->assertSee('1 of 3')
        ->keys(FIND_INPUT, 'Enter')
        ->assertSee('2 of 3');

    // Enter must advance the match without stealing focus into the editor —
    // otherwise the next Enter types a paragraph break into the manuscript.
    $activePlaceholder = $page->script('document.activeElement?.placeholder ?? null');
    expect($activePlaceholder)->toBe('Find in chapter...');

    // A second Enter keeps navigating and must not alter the scene content.
    $page->keys(FIND_INPUT, 'Enter')
        ->assertSee('3 of 3')
        ->wait(1);

    expect(Scene::where('chapter_id', $chapter->id)->first()->content)
        ->not->toContain('</p><p>zephyrion');
});

it('reports one result for a single occurrence in the book-wide find', function () {
    [$book, $chapter, $scenes] = createBookWithSceneContents([
        '<p>A single zephyrion appears in the entire book. Nothing else repeats.</p>',
    ]);
    $editorSelector = "#scene-{$scenes[0]->id} .ProseMirror";

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click($editorSelector)
        ->keys($editorSelector, 'Control+Shift+F')
        ->fill('input[placeholder="Search all chapters..."]', 'zephyrion')
        ->wait(1)
        ->assertSee('1 result in 1 ch.')
        ->assertNoJavaScriptErrors();
});
