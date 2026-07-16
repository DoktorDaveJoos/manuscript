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

it('does not restore stale book-wide results after the query is cleared', function () {
    [$book, $chapter, $scenes] = createBookWithSceneContents([
        '<p>A zephyrion appears once and should not return after clearing.</p>',
    ]);
    $editorSelector = "#scene-{$scenes[0]->id} .ProseMirror";
    $searchInput = 'input[placeholder="Search all chapters..."]';

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click($editorSelector)
        ->keys($editorSelector, 'Control+Shift+F');

    $page->script(<<<'JS'
        const originalFetch = window.fetch.bind(window);
        window.fetch = (input, init) => {
            const url = typeof input === 'string' ? input : (input?.url ?? '');

            if (url.endsWith('/search') && init?.method === 'POST') {
                const { signal: _signal, ...requestWithoutSignal } = init;
                return new Promise((resolve) => {
                    setTimeout(
                        () => resolve(originalFetch(input, requestWithoutSignal)),
                        800,
                    );
                });
            }

            return originalFetch(input, init);
        };
        JS);

    $page->fill($searchInput, 'zephyrion');
    $page->script(<<<'JS'
        setTimeout(() => {
            const input = document.querySelector('input[placeholder="Search all chapters..."]');
            const setter = Object.getOwnPropertyDescriptor(
                HTMLInputElement.prototype,
                'value',
            ).set;
            setter.call(input, '');
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }, 400);
        JS);
    $page->wait(2)->assertNoJavaScriptErrors();

    expect($page->script("document.querySelector('{$searchInput}').value"))
        ->toBe('')
        ->and($page->script("document.querySelectorAll('[data-search-result]').length"))
        ->toBe(0);
});

it('navigates to the exact occurrence selected in book-wide find', function () {
    [$book, $chapter, $scenes] = createBookWithSceneContents([
        '<p>First zephyrion marker. Second zephyrion target. Third zephyrion ending.</p>',
    ]);
    $editorSelector = "#scene-{$scenes[0]->id} .ProseMirror";
    $secondResult = sprintf(
        '[data-search-result][data-search-scene-id="%d"][data-search-occurrence-index="1"]',
        $scenes[0]->id,
    );

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->click($editorSelector)
        ->keys($editorSelector, 'Control+Shift+F')
        ->fill('input[placeholder="Search all chapters..."]', 'zephyrion')
        ->wait(1)
        ->click($secondResult)
        ->wait(1)
        ->assertNoJavaScriptErrors();

    $activeMatchIndex = $page->script(<<<JS
        (() => {
            const scene = document.querySelector('#scene-{$scenes[0]->id}');
            const matches = Array.from(scene.querySelectorAll('.search-highlight'));
            return matches.indexOf(scene.querySelector('.search-highlight-active'));
        })()
        JS);

    expect($activeMatchIndex)->toBe(1);
});

it('refreshes open editors after book-wide replacement before the next save', function () {
    [$book, $chapter, $scenes] = createBookWithSceneContents([
        '<p>The morning began quietly. Another morning followed.</p>',
    ]);
    $editorSelector = "#scene-{$scenes[0]->id} .ProseMirror";

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors();
    $page->script(<<<'JS'
        const originalFetch = window.fetch.bind(window);
        window.fetch = (input, init) => {
            const url = typeof input === 'string' ? input : (input?.url ?? '');

            if (url.includes('/scenes/') && init?.method === 'PUT') {
                return new Promise((resolve) => {
                    setTimeout(() => resolve(originalFetch(input, init)), 1500);
                });
            }

            return originalFetch(input, init);
        };
        JS);

    $page->type($editorSelector, 'Unsaved morning')
        ->keys($editorSelector, 'Control+Shift+R')
        ->fill('input[placeholder="Search all chapters..."]', 'morning')
        ->wait(1)
        ->fill('input[placeholder="Replace with..."]', 'evening')
        ->click('Replace all in book')
        ->wait(3)
        ->assertNoJavaScriptErrors();

    $editorText = $page->script(
        "document.querySelector('{$editorSelector}').innerText",
    );
    expect($editorText)
        ->toContain('Unsaved evening')
        ->not->toContain('morning');

    $page->type($editorSelector, 'Unsaved evening End')
        ->wait(2)
        ->assertNoJavaScriptErrors();

    expect($scenes[0]->fresh()->content)
        ->toContain('evening')
        ->toContain('End')
        ->not->toContain('morning');
});
