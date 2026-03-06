<?php

use App\Models\Book;
use App\Models\License;
use App\Models\Storyline;

test('pro json routes return 403 without licence', function () {
    $book = Book::factory()->withAi()->create();
    Storyline::factory()->for($book)->create();

    $this->postJson(route('books.ai.analyze', $book), ['type' => 'pacing'])
        ->assertForbidden()
        ->assertJsonPath('message', 'This feature requires an active Manuscript licence.');
});

test('canvas redirects to ai settings without licence', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.canvas', $book))
        ->assertRedirect(route('ai-settings.index'));
});

test('ai settings update returns 403 without licence', function () {
    $this->putJson(route('ai-settings.update', 'anthropic'), [
        'api_key' => 'sk-test',
        'enabled' => true,
    ])->assertForbidden();
});

test('ai settings test returns 403 without licence', function () {
    $this->postJson(route('ai-settings.test', 'anthropic'))
        ->assertForbidden();
});

test('pro routes succeed with active licence', function () {
    License::factory()->create();
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.canvas', $book))
        ->assertSuccessful();
});

test('ai settings index remains accessible without licence', function () {
    $this->get(route('ai-settings.index'))
        ->assertOk();
});

test('free routes remain accessible without licence', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.dashboard', $book))->assertOk();
    $this->get(route('books.characters', $book))->assertOk();
    $this->get(route('books.plot', $book))->assertOk();
});

test('book settings ai model update requires licence', function () {
    $book = Book::factory()->create();

    $this->putJson(route('books.settings.ai-model.update', $book), [
        'ai_model' => 'claude-sonnet-4-20250514',
        'ai_enabled' => true,
        'ai_provider' => 'anthropic',
    ])->assertForbidden();
});

test('writing style regenerate requires licence', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.settings.writing-style.regenerate', $book))
        ->assertForbidden();
});

test('manual writing style update remains free', function () {
    $book = Book::factory()->create();

    $this->putJson(route('books.settings.writing-style.update', $book), [
        'writing_style_text' => 'Test style.',
    ])->assertOk();
});
