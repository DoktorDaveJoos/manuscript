<?php

use App\Ai\Agents\EntityExtractor;
use App\Ai\Tools\LookupExistingEntities;
use App\Jobs\ExtractEntitiesJob;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Storyline;

test('entity extractor returns structured character and entity data', function () {
    EntityExtractor::fake();

    $book = Book::factory()->withAi()->create();

    $agent = new EntityExtractor($book);
    $response = $agent->prompt('Extract characters and entities from: John walked into the Brass Lantern tavern.');

    expect($response['characters'])->toBeArray()
        ->and($response['entities'])->toBeArray();

    EntityExtractor::assertPrompted(fn ($prompt) => true);
});

test('entity extractor includes book language in instructions', function () {
    $book = Book::factory()->create(['language' => 'de']);

    $agent = new EntityExtractor($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain('de');
});

test('entity extractor registers LookupExistingEntities scoped to its book', function () {
    $book = Book::factory()->create();

    $agent = new EntityExtractor($book);
    $tools = iterator_to_array($agent->tools());

    expect($tools)->toHaveCount(1)
        ->and($tools[0])->toBeInstanceOf(LookupExistingEntities::class);
});

test('extract entities job creates character and wiki entry records', function () {
    EntityExtractor::fake(function () {
        return [
            'characters' => [
                [
                    'name' => 'Hans Mueller',
                    'aliases' => ['Hans', 'Herr Mueller'],
                    'description' => 'The protagonist',
                    'role' => 'protagonist',
                ],
                [
                    'name' => 'Anna Schmidt',
                    'aliases' => null,
                    'description' => 'A supporting character',
                    'role' => 'supporting',
                ],
            ],
            'entities' => [
                [
                    'name' => 'The Brass Lantern',
                    'kind' => 'location',
                    'type' => 'Tavern',
                    'description' => 'A recurring meeting place for the protagonists.',
                ],
                [
                    'name' => 'The Order of the Silver Dawn',
                    'kind' => 'organization',
                    'type' => 'Secret Society',
                    'description' => 'A clandestine group driving the main conflict.',
                ],
            ],
        ];
    });

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => 'Hans Mueller walked into the Brass Lantern where Anna Schmidt was waiting.',
    ]);

    $job = new ExtractEntitiesJob($book, $chapter);
    $job->handle();

    expect($book->characters()->count())->toBe(2);

    $hans = $book->characters()->where('name', 'Hans Mueller')->first();
    expect($hans)->not->toBeNull()
        ->and($hans->is_ai_extracted)->toBeTrue()
        ->and($hans->aliases)->toBe(['Hans', 'Herr Mueller'])
        ->and($hans->description)->toBeNull()
        ->and($hans->ai_description)->toBe('The protagonist');

    expect($book->wikiEntries()->count())->toBe(2);

    $tavern = $book->wikiEntries()->where('name', 'The Brass Lantern')->first();
    expect($tavern)->not->toBeNull()
        ->and($tavern->kind->value)->toBe('location')
        ->and($tavern->type)->toBe('Tavern')
        ->and($tavern->is_ai_extracted)->toBeTrue();

    expect($tavern->description)->toBeNull()
        ->and($tavern->ai_description)->toBe('A recurring meeting place for the protagonists.');

    $order = $book->wikiEntries()->where('name', 'The Order of the Silver Dawn')->first();
    expect($order)->not->toBeNull()
        ->and($order->kind->value)->toBe('organization');

    // Assert pivot tables are populated
    expect($hans->chapters)->toHaveCount(1)
        ->and($hans->chapters->first()->id)->toBe($chapter->id)
        ->and($hans->chapters->first()->pivot->role)->toBe('protagonist');

    $anna = $book->characters()->where('name', 'Anna Schmidt')->first();
    expect($anna->chapters)->toHaveCount(1)
        ->and($anna->chapters->first()->pivot->role)->toBe('supporting');

    expect($tavern->chapters)->toHaveCount(1)
        ->and($tavern->chapters->first()->id)->toBe($chapter->id);

    expect($order->chapters)->toHaveCount(1)
        ->and($order->chapters->first()->id)->toBe($chapter->id);
});

test('extract entities job merges aliases instead of overwriting', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    // Create character with initial aliases
    $book->characters()->create([
        'name' => 'Hans Mueller',
        'aliases' => ['Hans', 'Herr Mueller'],
        'description' => null,
        'ai_description' => 'The protagonist',
        'is_ai_extracted' => true,
    ]);

    // Fake returns different aliases
    EntityExtractor::fake(function () {
        return [
            'characters' => [
                [
                    'name' => 'Hans Mueller',
                    'aliases' => ['Mueller', 'Hans'],
                    'description' => 'A brave hero',
                    'role' => 'protagonist',
                ],
            ],
            'entities' => [],
        ];
    });

    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => 'Hans Mueller arrived.',
    ]);

    $job = new ExtractEntitiesJob($book, $chapter);
    $job->handle();

    $hans = $book->characters()->where('name', 'Hans Mueller')->first();
    expect($hans->aliases)->toContain('Hans', 'Herr Mueller', 'Mueller')
        ->and($hans->aliases)->toHaveCount(3);
});

test('extract entities job keeps longer description', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $longDescription = 'Hans Mueller is a decorated former military officer turned reluctant hero who carries the weight of past decisions.';

    $book->characters()->create([
        'name' => 'Hans Mueller',
        'aliases' => [],
        'description' => null,
        'ai_description' => $longDescription,
        'is_ai_extracted' => true,
    ]);

    // Fake returns shorter description
    EntityExtractor::fake(function () {
        return [
            'characters' => [
                [
                    'name' => 'Hans Mueller',
                    'aliases' => [],
                    'description' => 'The protagonist',
                    'role' => 'protagonist',
                ],
            ],
            'entities' => [],
        ];
    });

    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => 'Hans Mueller arrived.',
    ]);

    $job = new ExtractEntitiesJob($book, $chapter);
    $job->handle();

    $hans = $book->characters()->where('name', 'Hans Mueller')->first();
    expect($hans->ai_description)->toBe($longDescription);
});

test('extract entities job updates first_appearance based on reader_order', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $chapter3 = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 3]);
    $chapter1 = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 1]);

    // Create character with first_appearance pointing to chapter 3
    $book->characters()->create([
        'name' => 'Hans Mueller',
        'aliases' => [],
        'description' => 'The protagonist',
        'is_ai_extracted' => true,
        'first_appearance' => $chapter3->id,
    ]);

    EntityExtractor::fake(function () {
        return [
            'characters' => [
                [
                    'name' => 'Hans Mueller',
                    'aliases' => [],
                    'description' => 'The protagonist',
                    'role' => 'protagonist',
                ],
            ],
            'entities' => [],
        ];
    });

    // Run extraction for chapter 1 (earlier reader_order)
    ChapterVersion::factory()->for($chapter1)->create([
        'is_current' => true,
        'content' => 'Hans Mueller arrived.',
    ]);

    $job = new ExtractEntitiesJob($book, $chapter1);
    $job->handle();

    $hans = $book->characters()->where('name', 'Hans Mueller')->first();
    expect($hans->first_appearance)->toBe($chapter1->id);
});

test('extract entities job strips html and caps content', function () {
    $capturedPrompt = null;

    EntityExtractor::fake(function ($prompt) use (&$capturedPrompt) {
        $capturedPrompt = $prompt;

        return [
            'characters' => [],
            'entities' => [],
        ];
    });

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => '<p>Hello <strong>world</strong></p>',
    ]);

    $job = new ExtractEntitiesJob($book, $chapter);
    $job->handle();

    expect($capturedPrompt)->not->toContain('<p>')
        ->and($capturedPrompt)->not->toContain('<strong>')
        ->and($capturedPrompt)->toContain('Hello world');
});

test('extract entities job skips chapters without content', function () {
    EntityExtractor::fake();

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => null,
    ]);

    $job = new ExtractEntitiesJob($book, $chapter);
    $job->handle();

    expect($book->characters()->count())->toBe(0)
        ->and($book->wikiEntries()->count())->toBe(0);
    EntityExtractor::assertNeverPrompted();
});

test('extract entities job does not overwrite manual character description', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $book->characters()->create([
        'name' => 'Hans Mueller',
        'aliases' => [],
        'description' => 'My custom notes about Hans.',
        'is_ai_extracted' => false,
    ]);

    EntityExtractor::fake(function () {
        return [
            'characters' => [
                [
                    'name' => 'Hans Mueller',
                    'aliases' => ['Hans'],
                    'description' => 'A brave hero who fights for justice in every chapter.',
                    'role' => 'protagonist',
                ],
            ],
            'entities' => [],
        ];
    });

    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => 'Hans Mueller arrived.',
    ]);

    $job = new ExtractEntitiesJob($book, $chapter);
    $job->handle();

    $hans = $book->characters()->where('name', 'Hans Mueller')->first();
    expect($hans->description)->toBe('My custom notes about Hans.')
        ->and($hans->ai_description)->toBe('A brave hero who fights for justice in every chapter.')
        ->and($hans->is_ai_extracted)->toBeFalse()
        ->and($hans->aliases)->toContain('Hans');
});

test('extract entities job does not overwrite manual wiki entry description', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $book->wikiEntries()->create([
        'name' => 'The Brass Lantern',
        'kind' => 'location',
        'description' => 'My notes about the tavern.',
        'is_ai_extracted' => false,
    ]);

    EntityExtractor::fake(function () {
        return [
            'characters' => [],
            'entities' => [
                [
                    'name' => 'The Brass Lantern',
                    'kind' => 'location',
                    'type' => 'Tavern',
                    'description' => 'A recurring meeting place mentioned throughout the story.',
                ],
            ],
        ];
    });

    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => 'They met at The Brass Lantern.',
    ]);

    $job = new ExtractEntitiesJob($book, $chapter);
    $job->handle();

    $entry = $book->wikiEntries()->where('name', 'The Brass Lantern')->first();
    expect($entry->description)->toBe('My notes about the tavern.')
        ->and($entry->ai_description)->toBe('A recurring meeting place mentioned throughout the story.')
        ->and($entry->is_ai_extracted)->toBeFalse();
});

test('extract entities job writes ai_description for new ai-extracted entries', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    EntityExtractor::fake(function () {
        return [
            'characters' => [
                [
                    'name' => 'New Character',
                    'aliases' => [],
                    'description' => 'Found in the text.',
                    'role' => 'mentioned',
                ],
            ],
            'entities' => [],
        ];
    });

    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => 'New Character appeared.',
    ]);

    $job = new ExtractEntitiesJob($book, $chapter);
    $job->handle();

    $character = $book->characters()->where('name', 'New Character')->first();
    expect($character->description)->toBeNull()
        ->and($character->ai_description)->toBe('Found in the text.')
        ->and($character->is_ai_extracted)->toBeTrue();
});

test('extract entities job matches manual entry with fuzzy name', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $book->wikiEntries()->create([
        'name' => 'The Crimson Blade',
        'kind' => 'item',
        'description' => 'A legendary sword.',
        'is_ai_extracted' => false,
    ]);

    EntityExtractor::fake(function () {
        return [
            'characters' => [],
            'entities' => [
                [
                    'name' => 'Crimson Blade',
                    'kind' => 'item',
                    'type' => 'Weapon',
                    'description' => 'A sword used in the battle scene.',
                ],
            ],
        ];
    });

    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => 'He drew the Crimson Blade.',
    ]);

    $job = new ExtractEntitiesJob($book, $chapter);
    $job->handle();

    expect($book->wikiEntries()->count())->toBe(1);

    $entry = $book->wikiEntries()->first();
    expect($entry->name)->toBe('The Crimson Blade')
        ->and($entry->description)->toBe('A legendary sword.')
        ->and($entry->ai_description)->toBe('A sword used in the battle scene.')
        ->and($entry->is_ai_extracted)->toBeFalse();
});
