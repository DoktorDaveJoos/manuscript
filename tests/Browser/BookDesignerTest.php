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
