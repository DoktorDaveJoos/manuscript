<?php

use App\Models\AppSetting;
use App\Models\Book;
use App\Models\Character;
use App\Models\WikiEntry;

test('proofreading config can be updated', function () {
    $config = Book::defaultProofreadingConfig();
    $config['spelling_enabled'] = false;

    $this->put('/settings/proofreading', ['config' => $config])
        ->assertOk()
        ->assertJson(['message' => 'Proofreading settings updated.']);

    $stored = json_decode(AppSetting::get('proofreading_config'), true);
    expect($stored['spelling_enabled'])->toBeFalse();
    expect($stored['grammar_enabled'])->toBeTrue();
});

test('proofreading config validates required fields', function () {
    $this->putJson('/settings/proofreading', ['config' => ['spelling_enabled' => true]])
        ->assertUnprocessable();
});

test('custom dictionary can be updated', function () {
    $book = Book::factory()->create();

    $this->put("/books/{$book->id}/settings/custom-dictionary", [
        'words' => ['Gandalf', 'Mordor'],
    ])->assertOk();

    $book->refresh();
    expect($book->custom_dictionary)->toBe(['Gandalf', 'Mordor']);
});

test('custom dictionary can be seeded from entities', function () {
    $book = Book::factory()->create();

    Character::factory()->for($book)->create([
        'name' => 'Aragorn',
        'aliases' => ['Strider', 'Elessar'],
    ]);

    WikiEntry::factory()->for($book)->create([
        'kind' => 'location',
        'name' => 'Rivendell',
    ]);

    $response = $this->post("/books/{$book->id}/settings/custom-dictionary/seed")
        ->assertOk();

    $words = $response->json('words');
    expect($words)->toContain('Aragorn', 'Strider', 'Elessar', 'Rivendell');
});

test('settings index includes proofreading config', function () {
    $this->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('proofreading_config'));
});

test('default proofreading config has correct structure', function () {
    $config = Book::defaultProofreadingConfig();

    expect($config)->toHaveKeys(['spelling_enabled', 'grammar_enabled', 'grammar_checks']);
    expect($config['spelling_enabled'])->toBeTrue();
    expect($config['grammar_enabled'])->toBeTrue();
    expect($config['grammar_checks'])->toHaveKeys(['illusion', 'so', 'thereIs', 'tooWordy', 'passive', 'weasel', 'adverb', 'cliches', 'eprime']);
    expect($config['grammar_checks']['illusion'])->toBeTrue();
    expect($config['grammar_checks']['passive'])->toBeFalse();
});

test('global proofreading config falls back to defaults when not set', function () {
    $config = Book::globalProofreadingConfig();

    expect($config)->toBe(Book::defaultProofreadingConfig());
});

test('global proofreading config reads from app settings when set', function () {
    $custom = Book::defaultProofreadingConfig();
    $custom['spelling_enabled'] = false;

    AppSetting::set('proofreading_config', json_encode($custom));

    $config = Book::globalProofreadingConfig();
    expect($config['spelling_enabled'])->toBeFalse();
});
