<?php

use App\Ai\Agents\EntityExtractor;
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

test('entity extractor includes book id in instructions', function () {
    $book = Book::factory()->create();

    $agent = new EntityExtractor($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain("The book ID is {$book->id}");
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
        ->and($hans->aliases)->toBe(['Hans', 'Herr Mueller']);

    expect($book->wikiEntries()->count())->toBe(2);

    $tavern = $book->wikiEntries()->where('name', 'The Brass Lantern')->first();
    expect($tavern)->not->toBeNull()
        ->and($tavern->kind->value)->toBe('location')
        ->and($tavern->type)->toBe('Tavern')
        ->and($tavern->is_ai_extracted)->toBeTrue();

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
        'description' => 'The protagonist',
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
        'description' => $longDescription,
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
    expect($hans->description)->toBe($longDescription);
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
