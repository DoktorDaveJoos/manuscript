<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\PlotCoachSession;
use App\Models\Storyline;
use App\Services\PlotCoachBatchService;
use Illuminate\Support\Facades\DB;

it('auto-attaches the POV character to character_chapter on chapter create', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $pov = Character::factory()->for($book, 'book')->create(['name' => 'Maja']);

    $service = app(PlotCoachBatchService::class);
    $batch = $service->apply($session, [[
        'type' => 'chapter',
        'data' => [
            'title' => 'Opening',
            'storyline_id' => $storyline->id,
            'pov_character_id' => $pov->id,
            // No character_ids supplied — POV must still land in the pivot.
        ],
    ]], 'test');

    $chapterId = $batch->payload['writes'][0]['id'];
    $povRole = DB::table('character_chapter')
        ->where('chapter_id', $chapterId)
        ->where('character_id', $pov->id)
        ->value('role');

    expect($povRole)->toBe('protagonist');
});

it('preserves supporting cast when POV is auto-included', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $pov = Character::factory()->for($book, 'book')->create(['name' => 'Maja']);
    $supporting = Character::factory()->for($book, 'book')->create(['name' => 'John']);

    $service = app(PlotCoachBatchService::class);
    $batch = $service->apply($session, [[
        'type' => 'chapter',
        'data' => [
            'title' => 'Opening',
            'storyline_id' => $storyline->id,
            'pov_character_id' => $pov->id,
            'character_ids' => [$supporting->id],
        ],
    ]], 'test');

    $chapterId = $batch->payload['writes'][0]['id'];
    $linkedIds = DB::table('character_chapter')
        ->where('chapter_id', $chapterId)
        ->pluck('character_id')
        ->all();

    expect($linkedIds)->toEqualCanonicalizing([$pov->id, $supporting->id]);
});

it('auto-attaches POV when an update sets pov_character_id on a chapter that did not have one', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $newPov = Character::factory()->for($book, 'book')->create(['name' => 'Maja']);

    // Existing chapter, no POV, no pivot rows.
    $chapter = Chapter::factory()
        ->for($book, 'book')
        ->for($storyline, 'storyline')
        ->create(['pov_character_id' => null]);

    $service = app(PlotCoachBatchService::class);
    $service->apply($session, [[
        'type' => 'chapter',
        'data' => [
            'id' => $chapter->id,
            'pov_character_id' => $newPov->id,
        ],
    ]], 'test');

    $povRole = DB::table('character_chapter')
        ->where('chapter_id', $chapter->id)
        ->where('character_id', $newPov->id)
        ->value('role');

    expect($povRole)->toBe('protagonist');
});

it('does not duplicate POV in the pivot when character_ids already includes the POV id, and forces role=protagonist', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $pov = Character::factory()->for($book, 'book')->create(['name' => 'Maja']);

    $service = app(PlotCoachBatchService::class);
    $batch = $service->apply($session, [[
        'type' => 'chapter',
        'data' => [
            'title' => 'Opening',
            'storyline_id' => $storyline->id,
            'pov_character_id' => $pov->id,
            'character_ids' => [$pov->id], // agent attached POV — service shouldn't duplicate; should ensure role
        ],
    ]], 'test');

    $chapterId = $batch->payload['writes'][0]['id'];
    $povRows = DB::table('character_chapter')
        ->where('chapter_id', $chapterId)
        ->where('character_id', $pov->id)
        ->get();

    expect($povRows)->toHaveCount(1);
    expect($povRows->first()->role)->toBe('protagonist');
});

it('undo of a pov-only update detaches the auto-included pivot row', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book, 'book')->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $newPov = Character::factory()->for($book, 'book')->create(['name' => 'Maja']);

    // Existing chapter, no POV, no pivot rows.
    $chapter = Chapter::factory()
        ->for($book, 'book')
        ->for($storyline, 'storyline')
        ->create(['pov_character_id' => null]);

    $service = app(PlotCoachBatchService::class);

    // Apply a pov-only update — auto-include adds the pivot row.
    $batch = $service->apply($session, [[
        'type' => 'chapter',
        'data' => [
            'id' => $chapter->id,
            'pov_character_id' => $newPov->id,
        ],
    ]], 'set pov');

    expect(DB::table('character_chapter')->where('chapter_id', $chapter->id)->count())->toBe(1);

    // Undo the update.
    $service->undoBatch($batch);

    // Both the FK and the pivot must revert together — no orphan row.
    $chapter->refresh();
    expect($chapter->pov_character_id)->toBeNull();
    expect(DB::table('character_chapter')->where('chapter_id', $chapter->id)->count())->toBe(0);
});
