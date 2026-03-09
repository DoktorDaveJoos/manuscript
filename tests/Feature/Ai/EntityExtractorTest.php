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
