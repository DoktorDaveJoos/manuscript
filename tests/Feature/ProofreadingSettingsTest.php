<?php

use App\Models\AppSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\WikiEntry;

test('book proofreading config can be updated', function () {
    $book = Book::factory()->create();
    $config = Book::defaultProofreadingConfig();
    $config['spelling_enabled'] = false;
    $config['style_checks']['filler'] = false;

    $this->put(route('books.settings.proofreading.update', $book), ['config' => $config])
        ->assertOk()
        ->assertJson(['message' => 'Proofreading settings updated.']);

    $stored = $book->refresh()->proofreading_config;
    expect($stored['spelling_enabled'])->toBeFalse();
    expect($stored['style_checks']['filler'])->toBeFalse();
    expect($stored['style_checks']['repetition'])->toBeTrue();
});

test('proofreading config validates required fields', function () {
    $book = Book::factory()->create();

    $this->putJson(route('books.settings.proofreading.update', $book), [
        'config' => ['spelling_enabled' => true],
    ])->assertUnprocessable();
});

test('proofreading page renders the book config', function () {
    $config = Book::defaultProofreadingConfig();
    $config['spelling_enabled'] = false;
    $book = Book::factory()->create(['proofreading_config' => $config]);

    $this->get(route('books.settings.proofreading', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/settings/proofreading')
            ->where('config.spelling_enabled', false)
        );
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

test('settings index no longer exposes proofreading config', function () {
    $this->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->missing('proofreading_config'));
});

test('default proofreading config has correct structure', function () {
    $config = Book::defaultProofreadingConfig();

    expect($config)->toHaveKeys(['spelling_enabled', 'style_checks']);
    expect($config['spelling_enabled'])->toBeTrue();
    expect($config['style_checks'])->toHaveKeys(['filler', 'weakVerb', 'filterWord', 'cliche', 'pattern', 'repetition', 'rhythm']);
    expect(collect($config['style_checks'])->every(fn ($enabled) => $enabled === true))->toBeTrue();
});

test('legacy write-good configs map to style checks on read', function () {
    $legacy = [
        'spelling_enabled' => false,
        'grammar_enabled' => true,
        'grammar_checks' => ['weasel' => true, 'passive' => false],
    ];
    $book = Book::factory()->create(['proofreading_config' => $legacy]);

    $config = $book->proofreadingConfig();
    expect($config['spelling_enabled'])->toBeFalse();
    expect($config['style_checks']['filler'])->toBeTrue();
    expect($config['style_checks']['repetition'])->toBeTrue();
    expect($config)->not->toHaveKey('grammar_enabled');
});

test('a legacy config with grammar disabled maps to all style checks off', function () {
    $legacy = [
        'spelling_enabled' => true,
        'grammar_enabled' => false,
        'grammar_checks' => [],
    ];
    $book = Book::factory()->create(['proofreading_config' => $legacy]);

    $config = $book->proofreadingConfig();
    expect(collect($config['style_checks'])->every(fn ($enabled) => $enabled === false))->toBeTrue();
    expect($config['spelling_enabled'])->toBeTrue();
});

test('a stored new-shape config gains newly added categories as enabled', function () {
    $partial = [
        'spelling_enabled' => true,
        'style_checks' => ['filler' => false],
    ];
    $book = Book::factory()->create(['proofreading_config' => $partial]);

    $config = $book->proofreadingConfig();
    expect($config['style_checks']['filler'])->toBeFalse();
    expect($config['style_checks']['rhythm'])->toBeTrue();
});

test('style ignored words can be updated and are normalized', function () {
    $book = Book::factory()->create();

    $this->put(route('books.settings.style-ignored-words.update', $book), [
        'words' => ['Eigentlich', 'plötzlich', 'eigentlich'],
    ])->assertOk();

    expect($book->refresh()->style_ignored_words)->toBe(['eigentlich', 'plötzlich']);
});

test('chapter data includes the style ignored words', function () {
    $book = Book::factory()->create(['style_ignored_words' => ['eigentlich']]);
    $chapter = Chapter::factory()->for($book)->create();

    $this->getJson(route('chapters.show.json', [$book, $chapter]))
        ->assertOk()
        ->assertJson(['styleIgnoredWords' => ['eigentlich']]);
});

test('book proofreading config falls back to defaults when not set', function () {
    $book = Book::factory()->create();

    expect($book->proofreadingConfig())->toBe(Book::defaultProofreadingConfig());
});

test('book proofreading config ignores the legacy global setting', function () {
    $custom = Book::defaultProofreadingConfig();
    $custom['spelling_enabled'] = false;
    AppSetting::set('proofreading_config', json_encode($custom));
    AppSetting::clearCache();

    $book = Book::factory()->create();

    expect($book->proofreadingConfig()['spelling_enabled'])->toBeTrue();
});

test('migration moves the global proofreading config onto books', function () {
    // The 2026-06 migration ran against legacy write-good-shaped configs.
    $legacy = [
        'spelling_enabled' => true,
        'grammar_enabled' => true,
        'grammar_checks' => ['weasel' => false, 'passive' => false],
    ];

    $own = $legacy;
    $own['grammar_checks']['passive'] = true;
    $withOwn = Book::factory()->create(['proofreading_config' => $own]);
    $without = Book::factory()->create();

    $global = $legacy;
    $global['grammar_enabled'] = false;
    AppSetting::set('proofreading_config', json_encode($global));

    $path = collect(glob(database_path('migrations/*_move_global_proofreading_config_to_books.php')))->sole();
    $migration = require $path;
    $migration->up();
    AppSetting::clearCache();

    expect($withOwn->refresh()->proofreading_config['grammar_checks']['passive'])->toBeTrue()
        ->and($withOwn->proofreading_config['grammar_enabled'])->toBeTrue()
        ->and($without->refresh()->proofreading_config['grammar_enabled'])->toBeFalse()
        ->and(AppSetting::get('proofreading_config'))->toBeNull();
});
