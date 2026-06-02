<?php

use App\Ai\Tools\RetrieveManuscriptContext;
use App\Enums\BeatStatus;
use App\Enums\PlotPointStatus;
use App\Enums\PlotPointType;
use App\Enums\WikiEntryKind;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Models\WikiEntry;
use Laravel\Ai\Tools\Request;

it('includes plot beats linked to the active chapter, grouped by parent plot point', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 3,
        'title' => 'The Confrontation',
    ]);

    $plotPoint = PlotPoint::factory()->for($book)->create([
        'title' => 'Hero confronts the antagonist',
        'description' => 'The pivotal showdown',
        'type' => PlotPointType::Conflict,
        'status' => PlotPointStatus::Planned,
    ]);

    $beatA = Beat::factory()->for($plotPoint)->create([
        'title' => 'Hero arrives at the keep',
        'description' => 'Tension rises as the gates open',
        'status' => BeatStatus::Planned,
        'sort_order' => 0,
    ]);
    $beatB = Beat::factory()->for($plotPoint)->create([
        'title' => 'Antagonist reveals the truth',
        'description' => 'The hero learns who is really pulling strings',
        'status' => BeatStatus::Fulfilled,
        'sort_order' => 1,
    ]);

    $chapter->beats()->attach([
        $beatA->id => ['sort_order' => 0],
        $beatB->id => ['sort_order' => 1],
    ]);

    $tool = new RetrieveManuscriptContext($book->id);
    $result = (string) $tool->handle(new Request(['chapter_id' => $chapter->id]));

    expect($result)
        ->toContain('## Plot Beats for This Chapter')
        ->toContain('Plot Point [conflict/planned] Hero confronts the antagonist')
        ->toContain('The pivotal showdown')
        ->toContain('[planned] Hero arrives at the keep')
        ->toContain('Tension rises as the gates open')
        ->toContain('[fulfilled] Antagonist reveals the truth');
});

it('handles plot points with a null type without crashing', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $plotPoint = PlotPoint::factory()->for($book)->create([
        'title' => 'Untyped plot point',
        'type' => null,
        'status' => PlotPointStatus::Planned,
    ]);

    $beat = Beat::factory()->for($plotPoint)->create(['title' => 'Untyped beat']);
    $chapter->beats()->attach($beat, ['sort_order' => 0]);

    $tool = new RetrieveManuscriptContext($book->id);
    $result = (string) $tool->handle(new Request(['chapter_id' => $chapter->id]));

    expect($result)
        ->toContain('Plot Point [—/planned] Untyped plot point')
        ->toContain('Untyped beat');
});

it('respects beat pivot order within a plot point group', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $plotPoint = PlotPoint::factory()->for($book)->create(['title' => 'Plot A']);

    $first = Beat::factory()->for($plotPoint)->create(['title' => 'First beat']);
    $second = Beat::factory()->for($plotPoint)->create(['title' => 'Second beat']);

    $chapter->beats()->attach([
        $second->id => ['sort_order' => 0],
        $first->id => ['sort_order' => 1],
    ]);

    $tool = new RetrieveManuscriptContext($book->id);
    $result = (string) $tool->handle(new Request(['chapter_id' => $chapter->id]));

    $secondPos = strpos($result, 'Second beat');
    $firstPos = strpos($result, 'First beat');

    expect($secondPos)->toBeLessThan($firstPos);
});

it('includes wiki entries linked to the active chapter, grouped by kind', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $location = WikiEntry::factory()->for($book)->create([
        'name' => 'The Brass Lantern',
        'kind' => WikiEntryKind::Location,
        'type' => 'Tavern',
        'description' => 'A recurring meeting place near the docks',
    ]);
    $organization = WikiEntry::factory()->for($book)->create([
        'name' => 'The Hollow Court',
        'kind' => WikiEntryKind::Organization,
        'type' => 'Council',
        'description' => 'A clandestine ruling body',
    ]);
    $lore = WikiEntry::factory()->for($book)->create([
        'name' => 'The Sundering',
        'kind' => WikiEntryKind::Lore,
        'type' => 'Cataclysm',
        'description' => 'The age when the moons split',
    ]);

    $chapter->wikiEntries()->attach([$location->id, $organization->id, $lore->id]);

    $tool = new RetrieveManuscriptContext($book->id);
    $result = (string) $tool->handle(new Request(['chapter_id' => $chapter->id]));

    expect($result)
        ->toContain('## Wiki Entries for This Chapter')
        ->toContain('### Locations')
        ->toContain('The Brass Lantern (Tavern): A recurring meeting place near the docks')
        ->toContain('### Organizations')
        ->toContain('The Hollow Court (Council)')
        ->toContain('### Lore')
        ->toContain('The Sundering (Cataclysm)')
        ->not->toContain('### Lores');
});

it('omits the plot beats section when no beats are linked to the chapter', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $tool = new RetrieveManuscriptContext($book->id);
    $result = (string) $tool->handle(new Request(['chapter_id' => $chapter->id]));

    expect($result)->not->toContain('## Plot Beats for This Chapter');
});

it('omits the wiki entries section when no wiki entries are linked to the chapter', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $tool = new RetrieveManuscriptContext($book->id);
    $result = (string) $tool->handle(new Request(['chapter_id' => $chapter->id]));

    expect($result)->not->toContain('## Wiki Entries for This Chapter');
});

it('omits chapter-scoped sections entirely when no chapter_id is provided', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $plotPoint = PlotPoint::factory()->for($book)->create();
    $beat = Beat::factory()->for($plotPoint)->create();
    $chapter->beats()->attach($beat, ['sort_order' => 0]);

    $entry = WikiEntry::factory()->for($book)->location()->create();
    $chapter->wikiEntries()->attach($entry);

    $tool = new RetrieveManuscriptContext($book->id);
    $result = (string) $tool->handle(new Request([]));

    expect($result)
        ->not->toContain('## Plot Beats for This Chapter')
        ->not->toContain('## Wiki Entries for This Chapter');
});

it('only includes beats and wiki entries linked to the requested chapter', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapterA = Chapter::factory()->for($book)->for($storyline)->create();
    $chapterB = Chapter::factory()->for($book)->for($storyline)->create();

    $plotPoint = PlotPoint::factory()->for($book)->create();
    $beatForA = Beat::factory()->for($plotPoint)->create(['title' => 'Beat in A']);
    $beatForB = Beat::factory()->for($plotPoint)->create(['title' => 'Beat in B']);

    $chapterA->beats()->attach($beatForA, ['sort_order' => 0]);
    $chapterB->beats()->attach($beatForB, ['sort_order' => 0]);

    $entryForA = WikiEntry::factory()->for($book)->create(['name' => 'Entry in A', 'kind' => WikiEntryKind::Location]);
    $entryForB = WikiEntry::factory()->for($book)->create(['name' => 'Entry in B', 'kind' => WikiEntryKind::Location]);

    $chapterA->wikiEntries()->attach($entryForA);
    $chapterB->wikiEntries()->attach($entryForB);

    $tool = new RetrieveManuscriptContext($book->id);
    $result = (string) $tool->handle(new Request(['chapter_id' => $chapterA->id]));

    expect($result)
        ->toContain('Beat in A')
        ->not->toContain('Beat in B')
        ->toContain('Entry in A')
        ->not->toContain('Entry in B');
});
