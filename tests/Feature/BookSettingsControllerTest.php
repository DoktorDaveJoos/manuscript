<?php

use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\License;

test('combined book settings page loads with all data', function () {
    $book = Book::factory()->create(['writing_style_text' => 'Formal, third person']);
    AiSetting::factory()->create(['provider' => AiProvider::Anthropic, 'enabled' => true]);

    $this->get(route('books.settings', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/book/index')
            ->has('book')
            ->has('enabled_providers')
            ->has('writing_style_display')
            ->has('rules', 6)
            ->has('storylines')
        );
});

test('update ai model saves correctly', function () {
    License::factory()->create();
    $book = Book::factory()->create();

    $this->putJson(route('books.settings.ai-model.update', $book), [
        'ai_model' => 'claude-sonnet-4-20250514',
        'ai_enabled' => true,
        'ai_provider' => 'anthropic',
    ])->assertOk()
        ->assertJsonPath('message', 'AI model settings updated.');

    $book->refresh();
    expect($book->ai_model)->toBe('claude-sonnet-4-20250514')
        ->and($book->ai_enabled)->toBeTrue();
});

test('update writing style saves', function () {
    $book = Book::factory()->create();

    $this->putJson(route('books.settings.writing-style.update', $book), [
        'writing_style_text' => 'Dark, moody prose with short sentences.',
    ])->assertOk();

    $book->refresh();
    expect($book->writing_style_text)->toBe('Dark, moody prose with short sentences.');
});

test('update prose pass rules saves', function () {
    $book = Book::factory()->create();

    $rules = Book::defaultProsePassRules();
    $rules[0]['enabled'] = false;

    $this->putJson(route('books.settings.prose-pass-rules.update', $book), [
        'rules' => $rules,
    ])->assertOk();

    $book->refresh();
    expect($book->prose_pass_rules[0]['enabled'])->toBeFalse();
});

test('books list is shared in inertia props', function () {
    $book = Book::factory()->create(['title' => 'Test Book']);

    $response = $this->get(route('books.settings', $book));
    $page = $response->original->getData()['page'];

    expect($page['props']['books_list'])->toHaveCount(1);
    expect($page['props']['books_list'][0]['title'])->toBe('Test Book');
});
