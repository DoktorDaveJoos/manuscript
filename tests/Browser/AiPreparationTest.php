<?php

use App\Models\AppSetting;
use App\Models\License;

beforeEach(function () {
    AppSetting::clearCache();
    License::factory()->create();
    AppSetting::set('show_ai_features', true);
});

it('opens the step selection modal from the AI dashboard', function () {
    [$book, , $preparation] = createBookWithChapters(2);
    $preparation->delete(); // fresh book, never prepared

    $page = visit("/books/{$book->id}/ai/dashboard");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Prepare Manuscript')
        ->click('Prepare Manuscript')
        ->assertSee('Semantic index')
        ->assertSee('Writing style')
        ->assertSee('Chapter analysis')
        ->assertSee('Wiki & characters')
        ->assertSee('Story bible')
        ->assertSee('Manuscript health')
        ->assertSee('Run selected steps');
});

it('locks dependent steps when chapter analysis is unchecked', function () {
    [$book, , $preparation] = createBookWithChapters(2);
    $preparation->delete(); // fresh book, never prepared

    $page = visit("/books/{$book->id}/ai/dashboard");

    $page->click('Prepare Manuscript')
        ->assertDontSee('Requires Chapter analysis')
        ->click('Chapter analysis')
        ->assertSee('Requires Chapter analysis')
        ->assertNoJavaScriptErrors();
});
