<?php

use App\Models\Book;

it('creates book-wide todos and tables from the slash menu', function () {
    $book = Book::factory()->create();

    $page = visit("/books/{$book->id}/notes");

    $layout = $page->script(<<<'JS'
        (() => {
            const panel = document.querySelector('[data-notes-panel]');
            const rect = panel.getBoundingClientRect();

            return {
                top: Math.round(rect.top),
                right: Math.round(rect.right),
                bottom: Math.round(rect.bottom),
                viewportWidth: window.innerWidth,
                viewportHeight: window.innerHeight,
            };
        })()
    JS);

    expect($layout['top'])->toBe(0)
        ->and($layout['right'])->toBe($layout['viewportWidth'])
        ->and($layout['bottom'])->toBe($layout['viewportHeight']);

    $page->assertNoJavaScriptErrors()
        ->assertSee('Notes & Research')
        ->fill('[data-notes-input]', '/')
        ->assertCount('[data-notes-slash-menu]', 1)
        ->click('Todo')
        ->fill('[data-notes-input]', 'Remember the eclipse')
        ->keys('[data-notes-input]', 'Enter')
        ->keys('[data-notes-input]', 'Enter')
        ->fill('[data-notes-input]', '/')
        ->assertCount('[data-notes-slash-menu]', 1)
        ->click('Table')
        ->assertCount('[data-notes-table]', 1)
        ->assertScript(
            '(() => { const icons = Array.from(document.querySelectorAll(`[data-notes-table] [data-icon="inline-start"]`)); return icons.length === 3 && icons.every((icon) => Math.round(icon.getBoundingClientRect().width) === 14); })()',
            true,
        )
        ->assertAttributeContains(
            '[data-notes-remove-table]',
            'class',
            'bg-neutral-bg',
        )
        ->assertAttributeContains(
            '[data-notes-remove-table]',
            'class',
            'text-delete',
        )
        ->fill('input[aria-label="Column 1 heading"]', 'Source')
        ->assertScript(
            'getComputedStyle(document.activeElement).borderTopLeftRadius',
            '8px',
        )
        ->click('[data-notes-canvas]')
        ->assertCount('[data-notes-input]', 1)
        ->assertScript(
            'document.activeElement?.matches("[data-notes-input]")',
            true,
        )
        ->fill('[data-notes-input]', 'Continue after table')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect($book->fresh()->notes)
        ->toContain('[ ] Remember the eclipse')
        ->toContain('| Source | Column 2 |')
        ->toContain('Continue after table');
});
