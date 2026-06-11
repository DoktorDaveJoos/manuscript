<?php

use App\Ai\Agents\ProseReviser;
use App\Models\AppSetting;
use App\Models\Book;
use App\Models\Chapter;

test('writing_style_display uses the book own text and ignores the global setting', function () {
    AppSetting::set('writing_style_text', 'GLOBAL STYLE');
    AppSetting::clearCache();

    $book = Book::factory()->create(['writing_style_text' => 'Book-specific style.']);

    expect($book->writing_style_display)->toBe('Book-specific style.');
});

test('writing_style_display stays empty when only the global setting exists', function () {
    AppSetting::set('writing_style_text', 'GLOBAL STYLE');
    AppSetting::clearCache();

    $book = Book::factory()->create();

    expect($book->writing_style_display)->toBe('');
});

test('prosePassRules returns defaults when the book has none saved', function () {
    $book = Book::factory()->create();

    expect($book->prosePassRules())->toEqual(Book::defaultProsePassRules());
});

test('prosePassRules merges missing default rules into a saved book configuration', function () {
    $partial = collect(Book::defaultProsePassRules())
        ->reject(fn ($rule) => $rule['key'] === 'shorten_long_sentences')
        ->values()
        ->all();

    $book = Book::factory()->create(['prose_pass_rules' => $partial]);

    $keys = collect($book->prosePassRules())->pluck('key')->all();

    expect($keys)->toContain('shorten_long_sentences');
});

test('prosePassRules ignores globally saved rules', function () {
    $global = collect(Book::defaultProsePassRules())
        ->map(fn ($rule) => [...$rule, 'enabled' => false])
        ->all();
    AppSetting::set('prose_pass_rules', json_encode($global));
    AppSetting::clearCache();

    $book = Book::factory()->create();

    expect(collect($book->prosePassRules())->every(fn ($rule) => $rule['enabled']))->toBeTrue();
});

test('generationApplicableProsePassRules respects book-disabled rules', function () {
    $rules = collect(Book::defaultProsePassRules())
        ->map(fn ($rule) => $rule['key'] === 'shorten_long_sentences' ? [...$rule, 'enabled' => false] : $rule)
        ->all();

    $book = Book::factory()->create(['prose_pass_rules' => $rules]);

    $enabled = collect($book->generationApplicableProsePassRules())
        ->filter(fn ($rule) => $rule['enabled'])
        ->pluck('key');

    expect($enabled)->not->toContain('shorten_long_sentences')
        ->and($enabled)->toContain('sentence_variety');
});

test('prose reviser applies the book own rules, not global ones', function () {
    $globallyDisabled = collect(Book::defaultProsePassRules())
        ->map(fn ($rule) => [...$rule, 'enabled' => false])
        ->all();
    AppSetting::set('prose_pass_rules', json_encode($globallyDisabled));
    AppSetting::clearCache();

    $bookRules = collect(Book::defaultProsePassRules())
        ->map(fn ($rule) => [...$rule, 'enabled' => $rule['key'] === 'tightening'])
        ->all();
    $book = Book::factory()->create(['prose_pass_rules' => $bookRules]);
    $chapter = Chapter::factory()->for($book)->create();

    $instructions = (string) (new ProseReviser($book, $chapter))->instructions();

    expect($instructions)->toContain('Prose tightening')
        ->and($instructions)->not->toContain('Sentence variety:');
});

test('migration moves global writing style and prose rules onto books', function () {
    $withOwn = Book::factory()->create([
        'writing_style_text' => 'Own style',
        'prose_pass_rules' => [['key' => 'own', 'label' => 'Own', 'description' => 'kept', 'enabled' => true]],
    ]);
    $without = Book::factory()->create();

    $globalRules = Book::defaultProsePassRules();
    $globalRules[0]['enabled'] = false;
    AppSetting::set('writing_style_text', 'Global style');
    AppSetting::set('prose_pass_rules', json_encode($globalRules));

    $path = collect(glob(database_path('migrations/*_move_global_prose_settings_to_books.php')))->sole();
    $migration = require $path;
    $migration->up();
    AppSetting::clearCache();

    expect($withOwn->refresh()->writing_style_text)->toBe('Own style')
        ->and($withOwn->prose_pass_rules)->toHaveCount(1)
        ->and($without->refresh()->writing_style_text)->toBe('Global style')
        ->and($without->prose_pass_rules[0]['enabled'])->toBeFalse()
        ->and(AppSetting::get('writing_style_text'))->toBeNull()
        ->and(AppSetting::get('prose_pass_rules'))->toBeNull();
});
