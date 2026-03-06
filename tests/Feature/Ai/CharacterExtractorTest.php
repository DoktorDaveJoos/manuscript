<?php

use App\Ai\Agents\CharacterExtractor;
use App\Jobs\ExtractCharactersJob;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Storyline;

test('character extractor returns structured character data', function () {
    CharacterExtractor::fake();

    $book = Book::factory()->withAi()->create();

    $agent = new CharacterExtractor($book);
    $response = $agent->prompt('Extract characters from: John walked into the room where Mary was waiting.');

    expect($response['characters'])->toBeArray();

    CharacterExtractor::assertPrompted(fn ($prompt) => true);
});

test('character extractor includes book language in instructions', function () {
    $book = Book::factory()->create(['language' => 'de']);

    $agent = new CharacterExtractor($book);
    $instructions = $agent->instructions();

    expect((string) $instructions)->toContain('de');
});

test('extract characters job creates character records', function () {
    CharacterExtractor::fake(function () {
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
        ];
    });

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => 'Hans Mueller walked into the room where Anna Schmidt was waiting.',
    ]);

    $job = new ExtractCharactersJob($book, $chapter);
    $job->handle();

    expect($book->characters()->count())->toBe(2);

    $hans = $book->characters()->where('name', 'Hans Mueller')->first();
    expect($hans)->not->toBeNull()
        ->and($hans->is_ai_extracted)->toBeTrue()
        ->and($hans->aliases)->toBe(['Hans', 'Herr Mueller']);
});

test('extract characters job skips chapters without content', function () {
    CharacterExtractor::fake();

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => null,
    ]);

    $job = new ExtractCharactersJob($book, $chapter);
    $job->handle();

    expect($book->characters()->count())->toBe(0);
    CharacterExtractor::assertNeverPrompted();
});
