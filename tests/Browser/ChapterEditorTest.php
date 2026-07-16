<?php

use App\Enums\ChapterStatus;
use App\Models\AppSetting;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\EditorialReview;
use App\Models\EditorialReviewChapterNote;
use App\Models\License;
use App\Models\PlotPoint;
use App\Models\Scene;
use App\Models\Storyline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('shows empty state when book has no chapters', function () {
    $book = Book::factory()->create(['title' => 'Empty Book']);

    $page = visit("/books/{$book->id}/editor");

    $page->assertNoJavaScriptErrors()
        ->assertSee('No chapters yet')
        ->assertSee('Create first chapter')
        ->assertSee('Import manuscript');
});

it('navigates to editor and displays chapter content', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/editor");

    $page->assertNoJavaScriptErrors()
        ->assertSee($chapters[0]->title);
});

it('shows the active book title beneath the app name in the sidebar', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['title' => 'The Glass Orchard']);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertSeeIn('[data-sidebar-book-title]', 'The Glass Orchard');
});

it('shows specific chapter when navigated directly', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $page = visit("/books/{$book->id}/chapters/{$chapters[1]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertSee($chapters[1]->title);
});

it('creates a new chapter from empty state', function () {
    $book = Book::factory()->create(['title' => 'New Chapter Book']);
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $page = visit("/books/{$book->id}/editor");

    $page->assertNoJavaScriptErrors()
        ->assertSee('No chapters yet')
        ->click('Create first chapter')
        ->assertNoJavaScriptErrors();

    expect(Chapter::where('book_id', $book->id)->count())->toBe(1);
});

it('renders chapter sidebar with multiple chapters', function () {
    [$book, $chapters] = createBookWithChapters(3);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertSee($chapters[0]->title)
        ->assertSee($chapters[1]->title)
        ->assertSee($chapters[2]->title);
});

it('updates the sidebar chapter title while editing without a page refresh', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];
    $newTitle = 'A Live Chapter Title';
    $chapterSelector = "[data-sidebar-chapter='{$chapter->id}']";
    $titleSelector = 'h1[contenteditable="true"]';

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->keys($titleSelector, ['Control+a', 'Backspace'])
        ->typeSlowly($titleSelector, $newTitle)
        ->assertSeeIn($chapterSelector, $newTitle);
});

it('updates the sidebar chapter word count while typing without a page refresh', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];
    $scene = Scene::where('chapter_id', $chapter->id)->firstOrFail();
    $chapterSelector = "[data-sidebar-chapter='{$chapter->id}']";
    $wordCountSelector = "{$chapterSelector} > span:last-child";
    $editorSelector = "#scene-{$scene->id} .ProseMirror";

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors();

    $wordCountBefore = (int) $page->text($wordCountSelector);

    $page->typeSlowly($editorSelector, ' live sidebar count words')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    $wordCountAfter = (int) $page->text($wordCountSelector);

    expect($wordCountAfter)->toBeGreaterThan($wordCountBefore);
});

it('saves scene content immediately without a debounce', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];
    $scene = Scene::where('chapter_id', $chapter->id)->firstOrFail();
    $editorSelector = "#scene-{$scene->id} .ProseMirror";
    $newContent = ' immediate content save';

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->typeSlowly($editorSelector, $newContent, 10);

    $deadline = microtime(true) + 1;
    do {
        $savedContent = $scene->fresh()->content;
        if (str_contains($savedContent, trim($newContent))) {
            break;
        }

        usleep(50_000);
    } while (microtime(true) < $deadline);

    expect($savedContent)->toContain(trim($newContent));
});

it('updates the editor title after renaming the chapter in the sidebar without a page refresh', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];
    $newTitle = 'Renamed From Sidebar';
    $chapterSelector = "[data-sidebar-chapter='{$chapter->id}']";
    $titleSelector = 'h1[contenteditable="true"]';

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->rightClick($chapterSelector)
        ->click('Rename')
        ->fill('input[type="text"]', $newTitle)
        ->click('Save')
        ->assertSeeIn($titleSelector, $newTitle)
        ->assertNoJavaScriptErrors();
});

it('caps long storyline names to a single truncated line in the sidebar', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $book->storylines()->first()->update([
        'name' => 'Alexander Schwarz Die Detox-Lüge und weitere sehr lange Titel',
    ]);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertCount('[data-storyline-header]', 1)
        ->assertCount('[data-storyline-header] .truncate', 1);
});

it('notes panel restores content after close and reopen', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="notes"]')
        ->fill('[data-notes-input]', 'Remember the villain twist')
        ->wait(1)
        ->click('[data-access-bar="notes"]')
        ->wait(1)
        ->click('[data-access-bar="notes"]')
        ->assertValue('[data-notes-input]', 'Remember the villain twist');

    expect($chapters[0]->fresh()->notes)->toBe('Remember the villain twist');
});

it('edits notes in a multi-line textarea so long lines wrap to the panel width', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="notes"]')
        ->fill('[data-notes-input]', 'A very long note line that should wrap onto multiple visual rows instead of scrolling sideways inside a single-line input field')
        ->wait(1)
        ->assertCount('textarea[data-notes-input]', 1);

    expect($chapters[0]->fresh()->notes)
        ->toBe('A very long note line that should wrap onto multiple visual rows instead of scrolling sideways inside a single-line input field');
});

it('shows the notes save status as an icon rather than text', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="notes"]')
        ->fill('[data-notes-input]', 'Trigger a save')
        ->assertCount('svg[data-notes-save-status]', 1);
});

it('keeps wrapped note navigation inside a block until the caret reaches its boundary', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $middleBlock = 'This is a deliberately long middle note block that wraps across several visual lines in the narrow notes panel.';
    $chapters[0]->update([
        'notes' => "First block\n{$middleBlock}\nLast block",
    ]);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="notes"]')
        ->assertValue('[data-notes-input]', 'Last block');

    $page->script("document.querySelector('[data-notes-input]').setSelectionRange(0, 0)");
    $page->keys('[data-notes-input]', 'ArrowUp')
        ->assertValue('[data-notes-input]', $middleBlock);

    $page->script("document.querySelector('[data-notes-input]').setSelectionRange(24, 24)");
    $page->keys('[data-notes-input]', 'ArrowUp')
        ->assertValue('[data-notes-input]', $middleBlock);

    $page->script("const input = document.querySelector('[data-notes-input]'); input.setSelectionRange(input.value.length, input.value.length)");
    $page->keys('[data-notes-input]', 'ArrowDown')
        ->assertValue('[data-notes-input]', 'Last block')
        ->assertNoJavaScriptErrors();
});

it('filters note slash commands by their translated labels', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="notes"]')
        ->fill('[data-notes-input]', '/list')
        ->assertSee('Bullet List')
        ->assertDontSee('No results')
        ->assertNoJavaScriptErrors();
});

it('enforces the note length limit before saving rejected content', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $maximumNotes = str_repeat('a', 10000);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="notes"]')
        ->assertAttribute('[data-notes-input]', 'maxlength', '10000')
        ->fill('[data-notes-input]', $maximumNotes)
        ->assertSee('Notes can contain up to 10,000 characters.')
        ->keys('[data-notes-input]', 'b')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect($page->script("document.querySelector('[data-notes-input]').value.length"))
        ->toBe(10000)
        ->and($chapters[0]->fresh()->notes)->toBe($maximumNotes);
});

it('shows a visible error without publishing notes when saving fails', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="notes"]');

    $page->script(<<<'JS'
        window.originalNotesFetch = window.fetch.bind(window);
        window.fetch = (input, init) => {
            const url = typeof input === 'string'
                ? input
                : input instanceof Request
                  ? input.url
                  : String(input ?? '');

            if (url.endsWith('/notes') && init?.method === 'PATCH') {
                return Promise.resolve(new Response('{}', {
                    status: 500,
                    headers: { 'Content-Type': 'application/json' },
                }));
            }

            return window.originalNotesFetch(input, init);
        };
        null;
        JS);

    $page->fill('[data-notes-input]', 'Keep this unsaved note visible')
        ->assertSee('Notes could not be saved. Your changes are still here—edit them to retry.')
        ->assertValue('[data-notes-input]', 'Keep this unsaved note visible')
        ->assertCount('[data-notes-error]', 1)
        ->assertCount('[data-notes-save-status="error"]', 1)
        ->assertNoJavaScriptErrors();

    expect($chapters[0]->fresh()->notes)->toBeNull();
});

it('shows a left border on the style analysis panel', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="style"]')
        ->assertCount('[data-style-panel].border-l.border-border-light', 1);
});

it('wiki panel remounts cleanly when switching panes in splitscreen', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id},{$chapters[1]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="wiki"]')
        ->assertNoJavaScriptErrors()
        ->click("[data-pane-chapter='{$chapters[1]->id}']")
        ->assertNoJavaScriptErrors()
        ->click("[data-pane-chapter='{$chapters[0]->id}']")
        ->assertNoJavaScriptErrors();
});

it('ai panel remounts cleanly when switching panes in splitscreen', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id},{$chapters[1]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="ai"]')
        ->assertNoJavaScriptErrors()
        ->click("[data-pane-chapter='{$chapters[1]->id}']")
        ->assertNoJavaScriptErrors()
        ->click("[data-pane-chapter='{$chapters[0]->id}']")
        ->assertNoJavaScriptErrors();
});

it('ai chat drawer remounts cleanly when switching panes in splitscreen', function () {
    [$book, $chapters] = createBookWithChapters(2);
    $conversationId = (string) Str::uuid7();

    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => null,
        'title' => 'Browser chat surface',
        'created_at' => now()->subMinutes(2),
        'updated_at' => now(),
    ]);

    $seedMessage = fn (string $role, string $content, int $minutesAgo) => [
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversationId,
        'user_id' => null,
        'agent' => 'BookChatAgent',
        'role' => $role,
        'content' => $content,
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now()->subMinutes($minutesAgo),
        'updated_at' => now()->subMinutes($minutesAgo),
    ];

    DB::table('agent_conversation_messages')->insert([
        $seedMessage('user', 'Help me sharpen this scene.', 2),
        $seedMessage('assistant', 'Start by clarifying what the protagonist risks.', 1),
    ]);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id},{$chapters[1]->id}");

    $storageKey = "manuscript:convo:book:{$book->id}:ch:{$chapters[0]->id}";
    $page->script("localStorage.setItem('{$storageKey}', '{$conversationId}')");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="chat"]')
        ->assertNoJavaScriptErrors()
        ->assertPresent('[data-testid="ai-chat-surface"].bg-surface-sidebar')
        ->assertPresent('[data-testid="ai-chat-message-scroller"][data-slot="message-scroller"][data-canvas="panel"].bg-surface-sidebar')
        ->assertPresent('[data-testid="ai-chat-composer"][data-slot="message-scroller-composer"]')
        ->assertPresent('[data-message-role="user"] [data-slot="message-header"]')
        ->assertPresent('[data-message-role="user"] [data-slot="bubble"][data-variant="muted"]')
        ->assertPresent('[data-message-role="assistant"] [data-slot="message-avatar"]')
        ->assertPresent('[data-message-role="assistant"] [data-slot="message-header"]')
        ->assertPresent('[data-message-role="assistant"] [data-slot="bubble"][data-variant="secondary"]')
        ->assertNotPresent('[data-slot="bubble-reactions"]')
        ->click("[data-pane-chapter='{$chapters[1]->id}']")
        ->assertNoJavaScriptErrors()
        ->click("[data-pane-chapter='{$chapters[0]->id}']")
        ->assertNoJavaScriptErrors();

    $borderWidths = $page->script(<<<'JS'
        (() => {
            const scroller = document.querySelector('[data-testid="ai-chat-message-scroller"]');
            const style = window.getComputedStyle(scroller);

            return [
                style.borderTopWidth,
                style.borderRightWidth,
                style.borderBottomWidth,
                style.borderLeftWidth,
            ];
        })()
    JS);

    expect($borderWidths)->toBe(['0px', '0px', '0px', '0px']);

    $chatColorsMatch = $page->script(<<<'JS'
        (() => {
            const panel = document.querySelector('[data-testid="ai-chat-surface"]');
            const scroller = document.querySelector('[data-testid="ai-chat-message-scroller"]');
            const user = document.querySelector('[data-message-role="user"] [data-slot="bubble-content"]');
            const assistant = document.querySelector('[data-message-role="assistant"] [data-slot="bubble-content"]');
            const panelStyle = window.getComputedStyle(panel);
            const scrollerStyle = window.getComputedStyle(scroller);
            const userStyle = window.getComputedStyle(user);
            const assistantStyle = window.getComputedStyle(assistant);

            return userStyle.backgroundColor !== assistantStyle.backgroundColor
                && userStyle.color === assistantStyle.color
                && assistantStyle.backgroundColor === scrollerStyle.backgroundColor
                && scrollerStyle.backgroundColor === panelStyle.backgroundColor;
        })()
    JS);

    expect($chatColorsMatch)->toBeTrue();

    $composerOverlaysTranscript = $page->script(<<<'JS'
        (() => {
            const scroller = document.querySelector('[data-testid="ai-chat-message-scroller"]');
            const viewport = scroller?.querySelector('[data-slot="message-scroller-viewport"]');
            const content = scroller?.querySelector('[data-slot="message-scroller-content"]');
            const composer = scroller?.querySelector('[data-testid="ai-chat-composer"]');
            const scrollButton = scroller?.querySelector('[data-slot="message-scroller-button"]');

            if (!scroller || !viewport || !content || !composer || !scrollButton) {
                return false;
            }

            const scrollerRect = scroller.getBoundingClientRect();
            const viewportRect = viewport.getBoundingClientRect();
            const composerRect = composer.getBoundingClientRect();
            const composerHeight = composerRect.height;
            const measuredHeight = Number.parseFloat(
                getComputedStyle(scroller).getPropertyValue('--message-scroller-composer-height'),
            );
            const contentInset = Number.parseFloat(
                getComputedStyle(content, '::after').height,
            );
            const scrollButtonBottom = Number.parseFloat(
                getComputedStyle(scrollButton).bottom,
            );

            return Math.abs(viewportRect.bottom - scrollerRect.bottom) < 1
                && composerRect.top < viewportRect.bottom
                && Math.abs(composerRect.bottom - scrollerRect.bottom) < 1
                && Math.abs(measuredHeight - composerHeight) < 1
                && contentInset >= composerHeight - 1
                && scrollButtonBottom >= composerHeight + 15;
        })()
    JS);

    expect($composerOverlaysTranscript)->toBeTrue();
});

it('ai panel shows chapter-specific editorial note in splitscreen', function () {
    License::factory()->create();

    [$book, $chapters] = createBookWithChapters(2);

    $review = EditorialReview::factory()->for($book)->create([
        'status' => 'completed',
    ]);

    EditorialReviewChapterNote::factory()
        ->for($review)
        ->for($chapters[0])
        ->create([
            'notes' => ['chapter_note' => 'Note for chapter one'],
        ]);

    EditorialReviewChapterNote::factory()
        ->for($review)
        ->for($chapters[1])
        ->create([
            'notes' => ['chapter_note' => 'Note for chapter two'],
        ]);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id},{$chapters[1]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="ai"]')
        ->assertSee('Note for chapter one')
        ->click("[data-pane-chapter='{$chapters[1]->id}']")
        ->assertSee('Note for chapter two')
        ->click("[data-pane-chapter='{$chapters[0]->id}']")
        ->assertSee('Note for chapter one');
});

it('plot panel connects a beat to the active chapter', function () {
    License::factory()->create();

    [$book, $chapters] = createBookWithChapters(1);
    $plotPoint = PlotPoint::factory()->for($book)->create(['title' => 'Inciting incident']);
    Beat::factory()->for($plotPoint, 'plotPoint')->create(['title' => 'Murder of the duke']);

    $page = visit("/books/{$book->id}/editor?panes={$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-access-bar="plot"]')
        // The search prompt only exists as an input placeholder — assertSee
        // matches text nodes, so assert on the input element itself.
        ->assertCount('input[placeholder="Search beats..."]', 1)
        ->fill('input[placeholder="Search beats..."]', 'Murder')
        ->wait(1)
        ->assertSee('Murder of the duke')
        ->assertSee('Inciting incident');
});

it('creates a scene from the chapter list "+ Add scene" button', function () {
    [$book, $chapters] = createBookWithChapters(1);

    expect(Scene::where('chapter_id', $chapters[0]->id)->count())->toBe(1);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click('[data-testid="add-scene-button"]')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect(Scene::where('chapter_id', $chapters[0]->id)->count())->toBe(2);
});

it('moves the editor selection into its own scene from the command palette', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];
    $scene = $chapter->scenes()->firstOrFail();
    $editorSelector = "#scene-{$scene->id} .ProseMirror";

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()->click($editorSelector);
    $page->script(<<<JS
        const editor = document.querySelector('{$editorSelector}');
        const range = document.createRange();
        range.selectNodeContents(editor);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        document.dispatchEvent(new Event('selectionchange'));
        JS);
    $page
        ->keys($editorSelector, 'Control+p')
        ->click('Make selection own scene')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    $scenes = $chapter->scenes()->orderBy('sort_order')->get();

    expect($scenes)->toHaveCount(2)
        ->and($scenes->first()->word_count)->toBe(0)
        ->and($scenes->last()->content)->toContain('Chapter 1 content');
});

it('moves the editor selection into its own chapter from the command palette', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];
    $scene = $chapter->scenes()->firstOrFail();
    $editorSelector = "#scene-{$scene->id} .ProseMirror";

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()->click($editorSelector);
    $page->script(<<<JS
        const editor = document.querySelector('{$editorSelector}');
        const range = document.createRange();
        range.selectNodeContents(editor);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        document.dispatchEvent(new Event('selectionchange'));
        JS);
    $page
        ->keys($editorSelector, 'Control+p')
        ->click('Make selection own chapter')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    $newChapter = $book->chapters()
        ->where('id', '!=', $chapter->id)
        ->with('scenes')
        ->firstOrFail();

    expect($book->chapters()->count())->toBe(2)
        ->and($chapter->scenes()->firstOrFail()->word_count)->toBe(0)
        ->and($newChapter->scenes->first()->content)->toContain('Chapter 1 content');
});

it('removes a deleted scene from the sidebar without a page refresh', function () {
    [$book, $chapters] = createBookWithChapters(1);

    Scene::factory()->for($chapters[0])->create([
        'title' => 'Second Scene',
        'sort_order' => 1,
        'word_count' => 0,
    ]);

    expect(Scene::where('chapter_id', $chapters[0]->id)->count())->toBe(2);

    $secondSceneId = Scene::where('chapter_id', $chapters[0]->id)
        ->where('sort_order', 1)
        ->value('id');

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertCount('[data-sidebar-scene]', 2)
        ->rightClick("[data-sidebar-scene='{$secondSceneId}']")
        ->click('Delete')
        ->wait(1)
        ->assertNoJavaScriptErrors()
        ->assertCount('[data-sidebar-scene]', 1);

    expect(Scene::where('chapter_id', $chapters[0]->id)->count())->toBe(1);
});

it('persists typewriter mode from the formatting toolbar', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertAttributeMissing('[data-testid="typewriter-toggle"]', 'aria-pressed')
        ->click('[data-testid="typewriter-toggle"]')
        ->assertAttribute('[data-testid="typewriter-toggle"]', 'aria-pressed', 'true')
        ->wait(1);

    AppSetting::clearCache();
    expect(AppSetting::get('typewriter_mode'))->toBeTrue();

    $page->refresh()
        ->assertAttribute('[data-testid="typewriter-toggle"]', 'aria-pressed', 'true')
        ->click('[data-testid="typewriter-toggle"]')
        ->wait(1);

    AppSetting::clearCache();
    expect(AppSetting::get('typewriter_mode'))->toBeFalse();

    $page->refresh()
        ->assertAttributeMissing('[data-testid="typewriter-toggle"]', 'aria-pressed');
});

it('moves a chapter to another storyline from the context menu without an error overlay', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];
    $target = Storyline::factory()->for($book)->create([
        'name' => 'Side Plot',
        'sort_order' => 1,
    ]);

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->rightClick("[data-sidebar-chapter='{$chapter->id}']")
        ->click('Move to')
        ->click("[role='menuitem']:has-text('Side Plot')")
        ->wait(1)
        // The sidebar must reflect the move immediately, without a page refresh
        ->assertSeeIn("[data-storyline-section='{$target->id}']", $chapter->title)
        // Inertia renders non-Inertia responses in a full-screen iframe modal
        ->assertNotPresent('iframe')
        ->assertNoJavaScriptErrors();

    expect($chapter->fresh()->storyline_id)->toBe($target->id);
});

it('updates the editor status badge after changing chapter status from the sidebar context menu', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];

    $page = visit("/books/{$book->id}/chapters/{$chapter->id}");

    $page->assertNoJavaScriptErrors()
        ->assertSeeIn('[data-testid="chapter-status-badge"]', 'Draft')
        ->rightClick("[data-sidebar-chapter='{$chapter->id}']")
        ->click('Status')
        ->click("[role='menuitem']:has-text('Revised')")
        ->wait(1)
        ->assertSeeIn('[data-testid="chapter-status-badge"]', 'Revised')
        ->assertNoJavaScriptErrors();

    expect($chapter->fresh()->status)->toBe(ChapterStatus::Revised);
});

it('shows status bubbles in the chapter sidebar by default', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertPresent("[data-sidebar-chapter='{$chapters[1]->id}'] [data-testid='chapter-status-dot']");
});

it('hides status bubbles via the chapter list display options', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertPresent("[data-sidebar-chapter='{$chapters[1]->id}'] [data-testid='chapter-status-dot']")
        ->click('[data-testid="chapter-list-display-options"]')
        ->click('[data-testid="display-toggle-status-bubbles"]')
        ->wait(1)
        ->assertMissing("[data-sidebar-chapter='{$chapters[1]->id}'] [data-testid='chapter-status-dot']")
        ->assertNoJavaScriptErrors();

    AppSetting::clearCache();
    expect(AppSetting::get('show_status_bubbles'))->toBeFalse();
});

it('hides word counts via the chapter list display options', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertPresent("[data-sidebar-chapter='{$chapters[1]->id}'] [data-testid='chapter-word-count']")
        ->click('[data-testid="chapter-list-display-options"]')
        ->click('[data-testid="display-toggle-word-count"]')
        ->wait(1)
        ->assertMissing("[data-sidebar-chapter='{$chapters[1]->id}'] [data-testid='chapter-word-count']")
        ->assertNoJavaScriptErrors();

    AppSetting::clearCache();
    expect(AppSetting::get('show_word_count'))->toBeFalse();
});

it('switches word counts between compact and raw formats', function () {
    [$book, $chapters] = createBookWithChapters(2);
    $chapters[1]->update(['word_count' => 1700]);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertSeeIn("[data-sidebar-chapter='{$chapters[1]->id}']", '1.7k')
        ->click('[data-testid="chapter-list-display-options"]')
        ->click('[data-testid="display-toggle-compact-word-count"]')
        ->wait(1)
        ->assertSeeIn("[data-sidebar-chapter='{$chapters[1]->id}']", '1,700')
        ->assertNoJavaScriptErrors();

    AppSetting::clearCache();
    expect(AppSetting::get('compact_word_count'))->toBeFalse();
});

it('collapses the publish nav group by default and keeps the story group open', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->assertPresent("[data-testid='nav-group-story-content']")
        ->assertNotPresent("[data-testid='nav-group-publish-content']");
});

it('expands the publish nav group on click revealing typesetting and export', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click("[data-testid='nav-group-publish']")
        ->assertPresent("[data-testid='nav-group-publish-content']")
        ->assertSee('Typesetting')
        ->assertSee('Export');
});

it('auto-expands the publish nav group when landing on a route inside it', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/design");

    $page->assertNoJavaScriptErrors()
        ->assertPresent("[data-testid='nav-group-publish-content']");
});

it('persists a manually toggled nav group state across a reload', function () {
    [$book, $chapters] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/chapters/{$chapters[0]->id}");

    $page->assertNoJavaScriptErrors()
        ->click("[data-testid='nav-group-publish']")
        ->assertPresent("[data-testid='nav-group-publish-content']")
        ->refresh()
        ->assertPresent("[data-testid='nav-group-publish-content']");
});
