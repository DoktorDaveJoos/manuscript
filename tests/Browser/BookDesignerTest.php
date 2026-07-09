<?php

use App\Models\DesignTemplate;
use App\Models\License;

beforeEach(function () {
    License::factory()->create();
});

it('renders the book designer with template picker, panels and preview', function () {
    [$book] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/design");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Page setup')
        ->assertSee('Trim size')
        ->assertSee('Text layout')
        ->assertSee('Body text')
        ->assertSee('Apply to book');
});

it('creates an editable copy when a built-in template is changed', function () {
    [$book] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/design");

    $page->assertNoJavaScriptErrors()
        ->select('[data-testid="design-font-pairing"]', 'modern-mixed')
        ->wait(1)
        ->assertSee('Classic (Custom)');

    expect(DesignTemplate::query()->count())->toBe(1)
        ->and(DesignTemplate::query()->first()->settings['typography']['font_pairing'])->toBe('modern-mixed');
});

it('applies the selected template to the book', function () {
    [$book] = createBookWithChapters(1);
    DesignTemplate::factory()->create(['name' => 'My Look']);

    $page = visit("/books/{$book->id}/design");

    $template = DesignTemplate::query()->first();

    $page->assertNoJavaScriptErrors()
        ->select('[data-testid="design-template-select"]', 'custom:'.$template->id)
        ->click('Apply to book')
        ->wait(1);

    expect($book->fresh()->export_settings['template'])->toBe('custom:'.$template->id);
});

it('opens the book like a printed one: page 1 alone on the recto', function () {
    [$book] = createBookWithChapters(3);

    $page = visit("/books/{$book->id}/design");

    // In a bound book, page 1 is a recto standing alone; facing pairs are
    // (2,3), (4,5), … — never (1,2).
    $page->assertNoJavaScriptErrors()
        ->wait(3)
        ->assertPresent('[data-testid="design-spread-blank"]')
        ->assertSee('Page 1 of')
        ->click('[aria-label="Next spread"]')
        ->assertSee('Pages 2–3 of');
});

it('sizes preview pages by the bleed-grown sheet, not the raw trim', function () {
    [$book] = createBookWithChapters(1);
    $settings = (new \App\Services\Export\Templates\ClassicTemplate)->designSettings();
    $settings['page']['bleed'] = 5.0;
    $template = DesignTemplate::factory()->create(['settings' => $settings]);
    $book->update(['export_settings' => ['template' => $template->slug()]]);

    $page = visit("/books/{$book->id}/design");

    // Pocket trim 127×203.2 grown by 5 mm bleed on every edge → 137×213.2.
    $page->assertNoJavaScriptErrors()
        ->wait(3)
        ->assertAttribute('[data-testid="design-spread"]', 'data-page-ratio', number_format(213.2 / 137, 3));
});

it('survives an invalid edit to a built-in template without creating a copy', function () {
    [$book] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/design");

    $page->assertNoJavaScriptErrors()
        ->fill('[aria-label="Top margin"]', '1') // below the 5 mm minimum → server rejects
        ->wait(1)
        ->assertSee('Page setup')
        ->assertSee('Trim size');

    expect(DesignTemplate::query()->count())->toBe(0);
});

it('coalesces rapid edits on a built-in into one custom template', function () {
    [$book] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/design");

    $page->assertNoJavaScriptErrors()
        ->select('[data-testid="design-font-pairing"]', 'modern-mixed')
        ->select('[data-testid="design-trim-size"]', '6x9')
        ->wait(1);

    $templates = DesignTemplate::all();
    expect($templates)->toHaveCount(1)
        ->and($templates->first()->settings['typography']['font_pairing'])->toBe('modern-mixed')
        ->and($templates->first()->settings['page']['trim_size'])->toBe('6x9');
});

it('shows a built-in template\'s real heading size even when off the preset list', function () {
    [$book] = createBookWithChapters(1);

    $page = visit("/books/{$book->id}/design");

    // Modern's actual heading scale is 1.2em — not one of the preset options.
    $page->assertNoJavaScriptErrors()
        ->select('[data-testid="design-template-select"]', 'modern')
        ->wait(1)
        ->assertValue('[data-testid="design-heading-scale"]', '1.2');
});
