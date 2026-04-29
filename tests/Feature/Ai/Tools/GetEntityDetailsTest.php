<?php

use App\Ai\Tools\Plot\GetEntityDetails;
use App\Enums\WikiEntryKind;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\PlotPoint;
use App\Models\WikiEntry;
use Laravel\Ai\Tools\Request;

it('returns full plot point descriptions for the requested ids', function () {
    $book = Book::factory()->create();
    $other = PlotPoint::factory()->for($book)->create([
        'title' => 'Other plot point',
        'description' => 'Should not appear.',
    ]);
    $target = PlotPoint::factory()->for($book)->create([
        'title' => 'Pulled to Jakutsk',
        'description' => str_repeat('Maja boards the plane in heavy snow. ', 10),
    ]);

    $tool = new GetEntityDetails($book->id);
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'plot_point_ids' => json_encode([$target->id]),
    ]));

    expect($result)
        ->toContain('## Plot points')
        ->toContain("id={$target->id}")
        ->toContain('Pulled to Jakutsk')
        ->toContain('Maja boards the plane in heavy snow.')
        ->not->toContain('Other plot point')
        ->not->toContain("id={$other->id}");
});

it('returns full beat descriptions for the requested ids', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->for($book)->create();
    $beat = Beat::factory()->for($plotPoint)->create([
        'title' => 'First cut',
        'description' => 'The colleague vanishes into the white corridor light.',
    ]);

    $tool = new GetEntityDetails($book->id);
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'beat_ids' => json_encode([$beat->id]),
    ]));

    expect($result)
        ->toContain('## Beats')
        ->toContain("id={$beat->id}")
        ->toContain('First cut')
        ->toContain('white corridor light');
});

it('returns full character descriptions including ai_description and aliases', function () {
    $book = Book::factory()->create();
    $character = Character::factory()->for($book)->create([
        'name' => 'Maja',
        'aliases' => ['Mascha'],
        'description' => 'Research chemist.',
        'ai_description' => "### Wound\nShe let Hofmann take the blame.",
    ]);

    $tool = new GetEntityDetails($book->id);
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'character_ids' => json_encode([$character->id]),
    ]));

    expect($result)
        ->toContain('## Characters')
        ->toContain("id={$character->id}")
        ->toContain('Maja')
        ->toContain('aliases: Mascha')
        ->toContain('Research chemist.')
        ->toContain('### Wound')
        ->toContain('Hofmann');
});

it('returns full wiki entry descriptions with kind label', function () {
    $book = Book::factory()->create();
    $entry = WikiEntry::factory()->for($book)->create([
        'name' => 'The interface phenomenon',
        'kind' => WikiEntryKind::Lore,
        'description' => 'A communication channel that opens only under controlled error conditions.',
    ]);

    $tool = new GetEntityDetails($book->id);
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'wiki_entry_ids' => json_encode([$entry->id]),
    ]));

    expect($result)
        ->toContain('## Wiki entries')
        ->toContain("id={$entry->id}")
        ->toContain('[lore]')
        ->toContain('The interface phenomenon')
        ->toContain('controlled error conditions');
});

it('combines multiple entity types in one call', function () {
    $book = Book::factory()->create();
    $plotPoint = PlotPoint::factory()->for($book)->create(['title' => 'Lab accident']);
    $beat = Beat::factory()->for($plotPoint)->create(['title' => 'The breach']);
    $character = Character::factory()->for($book)->create(['name' => 'Maja']);

    $tool = new GetEntityDetails($book->id);
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'plot_point_ids' => json_encode([$plotPoint->id]),
        'beat_ids' => json_encode([$beat->id]),
        'character_ids' => json_encode([$character->id]),
    ]));

    expect($result)
        ->toContain('## Plot points')
        ->toContain('Lab accident')
        ->toContain('## Beats')
        ->toContain('The breach')
        ->toContain('## Characters')
        ->toContain('Maja');
});

it('ignores ids that belong to other books', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $foreign = PlotPoint::factory()->for($bookB)->create(['title' => 'Belongs to B']);

    $tool = new GetEntityDetails($bookA->id);
    $result = (string) $tool->handle(new Request([
        'plot_point_ids' => json_encode([$foreign->id]),
    ]));

    expect($result)
        ->toBe('No matching entities found for the given ids on this book.');
});

it('rejects an id list that exceeds the per-type cap', function () {
    $book = Book::factory()->create();

    $tool = new GetEntityDetails($book->id);
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'plot_point_ids' => json_encode(range(1, 11)),
    ]));

    expect($result)->toContain('plot_point_ids has more than 10 ids');
});

it('errors when no id arrays are passed', function () {
    $book = Book::factory()->create();

    $tool = new GetEntityDetails($book->id);
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
    ]));

    expect($result)->toContain('pass at least one id');
});

it('errors when book_id is missing or invalid', function () {
    // Constructor binding means a missing book_id at construction is a
    // hard programmer error, not a runtime check. The "not found" branch
    // still applies for an id pointing at a non-existent book.
    $book = Book::factory()->create();
    $tool = new GetEntityDetails($book->id + 999);

    $notFound = (string) $tool->handle(new Request([
        'plot_point_ids' => json_encode([1]),
    ]));
    expect($notFound)->toContain('not found');
});

it('renders a placeholder when description is empty', function () {
    $book = Book::factory()->create();
    $point = PlotPoint::factory()->for($book)->create([
        'title' => 'Sketch only',
        'description' => null,
    ]);

    $tool = new GetEntityDetails($book->id);
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'plot_point_ids' => json_encode([$point->id]),
    ]));

    expect($result)
        ->toContain('Sketch only')
        ->toContain('_(no description)_');
});

it('returns chapters by id with summary', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create([
        'title' => 'Chapter 1',
        'summary' => 'Maja arrives in Jakutsk and meets the contact.',
    ]);

    $tool = new GetEntityDetails($book->id);
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'chapter_ids' => json_encode([$chapter->id]),
    ]));

    expect($result)
        ->toContain('## Chapters')
        ->toContain("id={$chapter->id}")
        ->toContain('Chapter 1')
        ->toContain('Maja arrives in Jakutsk');
});

it('returns an explicit parse error when an id list is malformed JSON', function () {
    $book = Book::factory()->create();

    $result = (string) (new GetEntityDetails($book->id))->handle(new Request([
        'plot_point_ids' => '[12, 15',
    ]));

    expect($result)
        ->toContain('plot_point_ids')
        ->toContain('JSON')
        ->not->toContain('## Plot points');
});
