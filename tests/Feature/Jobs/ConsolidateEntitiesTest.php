<?php

use App\Ai\Agents\EntityConsolidator;
use App\Jobs\Preparation\ConsolidateEntities;
use App\Models\AiPreparation;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\WikiEntry;

test('merges duplicate characters', function () {
    $book = Book::factory()->withAi()->create();
    $chapter1 = Chapter::factory()->for($book)->create(['reader_order' => 1]);
    $chapter2 = Chapter::factory()->for($book)->create(['reader_order' => 2]);

    $canonical = Character::factory()->aiExtracted()->for($book)->create([
        'name' => 'Maja Paulsen',
        'aliases' => ['Maja'],
        'description' => 'A young woman who leads the resistance.',
        'first_appearance' => $chapter2->id,
    ]);
    $canonical->chapters()->attach($chapter2->id, ['role' => 'supporting']);

    $duplicate = Character::factory()->aiExtracted()->for($book)->create([
        'name' => 'Paulsen',
        'aliases' => [],
        'description' => 'Short desc.',
        'first_appearance' => $chapter1->id,
    ]);
    $duplicate->chapters()->attach($chapter1->id, ['role' => 'protagonist']);

    EntityConsolidator::fake(function () use ($canonical, $duplicate) {
        return [
            'character_merges' => [
                [
                    'canonical_id' => $canonical->id,
                    'duplicate_ids' => [$duplicate->id],
                    'canonical_name' => 'Maja Paulsen',
                    'merged_aliases' => ['Paulsen', 'Maja'],
                ],
            ],
            'entity_merges' => [],
        ];
    });

    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'running',
        'current_phase' => 'chapter_analysis',
        'current_phase_progress' => 0,
        'current_phase_total' => 1,
    ]);

    $job = new ConsolidateEntities($book, $preparation);
    $job->handle();

    // Canonical should be updated
    $canonical->refresh();
    expect($canonical->name)->toBe('Maja Paulsen')
        ->and($canonical->aliases)->toContain('Paulsen')
        ->and($canonical->aliases)->toContain('Maja')
        ->and($canonical->aliases)->not->toContain('Maja Paulsen')
        ->and($canonical->description)->toBe('A young woman who leads the resistance.')
        ->and($canonical->first_appearance)->toBe($chapter1->id);

    // Canonical should have both chapter pivots
    $pivots = $canonical->chapters()->get()->keyBy('id');
    expect($pivots)->toHaveCount(2)
        ->and($pivots[$chapter1->id]->pivot->role)->toBe('protagonist')
        ->and($pivots[$chapter2->id]->pivot->role)->toBe('supporting');

    // Duplicate should be deleted
    expect(Character::find($duplicate->id))->toBeNull();

    // Progress should be incremented
    $preparation->refresh();
    expect($preparation->current_phase_progress)->toBe(1);
});

test('resolves character role priority (protagonist > supporting > mentioned)', function () {
    $book = Book::factory()->withAi()->create();
    $chapter = Chapter::factory()->for($book)->create(['reader_order' => 1]);

    $canonical = Character::factory()->aiExtracted()->for($book)->create([
        'name' => 'John Smith',
        'description' => 'A hero.',
    ]);
    $canonical->chapters()->attach($chapter->id, ['role' => 'mentioned']);

    $duplicate = Character::factory()->aiExtracted()->for($book)->create([
        'name' => 'Smith',
        'description' => 'Short.',
    ]);
    $duplicate->chapters()->attach($chapter->id, ['role' => 'protagonist']);

    EntityConsolidator::fake(function () use ($canonical, $duplicate) {
        return [
            'character_merges' => [
                [
                    'canonical_id' => $canonical->id,
                    'duplicate_ids' => [$duplicate->id],
                    'canonical_name' => 'John Smith',
                    'merged_aliases' => ['Smith'],
                ],
            ],
            'entity_merges' => [],
        ];
    });

    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'running',
        'current_phase_progress' => 0,
        'current_phase_total' => 1,
    ]);

    $job = new ConsolidateEntities($book, $preparation);
    $job->handle();

    $canonical->refresh();
    $pivot = $canonical->chapters()->where('chapter_id', $chapter->id)->first();
    expect($pivot->pivot->role)->toBe('protagonist');
});

test('merges duplicate wiki entries', function () {
    $book = Book::factory()->withAi()->create();
    $chapter1 = Chapter::factory()->for($book)->create(['reader_order' => 1]);
    $chapter2 = Chapter::factory()->for($book)->create(['reader_order' => 2]);

    $canonical = WikiEntry::factory()->aiExtracted()->organization()->for($book)->create([
        'name' => 'Green Zone Protection Party',
        'description' => 'A political party that governs the Green Zone with strict environmental policies.',
        'metadata' => ['aliases' => []],
        'first_appearance' => $chapter2->id,
    ]);
    $canonical->chapters()->attach($chapter2->id);

    $duplicate = WikiEntry::factory()->aiExtracted()->organization()->for($book)->create([
        'name' => 'GZP',
        'description' => 'The GZP.',
        'metadata' => null,
        'first_appearance' => $chapter1->id,
    ]);
    $duplicate->chapters()->attach($chapter1->id);

    EntityConsolidator::fake(function () use ($canonical, $duplicate) {
        return [
            'character_merges' => [],
            'entity_merges' => [
                [
                    'canonical_id' => $canonical->id,
                    'duplicate_ids' => [$duplicate->id],
                    'canonical_name' => 'Green Zone Protection Party',
                    'merged_aliases' => ['GZP'],
                ],
            ],
        ];
    });

    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'running',
        'current_phase_progress' => 0,
        'current_phase_total' => 1,
    ]);

    $job = new ConsolidateEntities($book, $preparation);
    $job->handle();

    $canonical->refresh();
    expect($canonical->name)->toBe('Green Zone Protection Party')
        ->and($canonical->metadata['aliases'])->toContain('GZP')
        ->and($canonical->description)->toBe('A political party that governs the Green Zone with strict environmental policies.')
        ->and($canonical->first_appearance)->toBe($chapter1->id);

    // Canonical should have both chapter pivots
    expect($canonical->chapters()->count())->toBe(2);

    // Duplicate should be deleted
    expect(WikiEntry::find($duplicate->id))->toBeNull();
});

test('skips non-AI-extracted entities', function () {
    EntityConsolidator::fake(function () {
        return ['character_merges' => [], 'entity_merges' => []];
    });

    $book = Book::factory()->withAi()->create();

    // Only non-AI-extracted characters — should skip (fewer than 2 AI entities)
    Character::factory()->for($book)->create(['name' => 'Manual Char', 'is_ai_extracted' => false]);
    Character::factory()->for($book)->create(['name' => 'Manual Char 2', 'is_ai_extracted' => false]);

    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'running',
        'current_phase_progress' => 0,
        'current_phase_total' => 1,
    ]);

    $job = new ConsolidateEntities($book, $preparation);
    $job->handle();

    // Progress incremented but no AI call made (fewer than 2 AI entities)
    $preparation->refresh();
    expect($preparation->current_phase_progress)->toBe(1);
});

test('handles batch cancellation', function () {
    $book = Book::factory()->withAi()->create();

    Character::factory()->aiExtracted()->for($book)->count(2)->create();

    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'running',
        'current_phase_progress' => 0,
        'current_phase_total' => 1,
    ]);

    // Create a real batch with a no-op job, then cancel it
    $noop = new \App\Jobs\Preparation\PhaseTransition($preparation);
    $batch = \Illuminate\Support\Facades\Bus::batch([$noop])->allowFailures()->dispatch();
    $batch->cancel();

    // Now manually run ConsolidateEntities with the cancelled batch ID
    $preparation->update(['current_phase_progress' => 0]);
    $job = new ConsolidateEntities($book, $preparation);
    $job->withBatchId($batch->id);
    $job->handle();

    $preparation->refresh();
    expect($preparation->current_phase_progress)->toBe(0);
});

test('handles non-transient errors gracefully', function () {
    EntityConsolidator::fake(function () {
        throw new \RuntimeException('Model returned invalid JSON');
    });

    $book = Book::factory()->withAi()->create();

    Character::factory()->aiExtracted()->for($book)->count(3)->create();

    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'running',
        'current_phase_progress' => 0,
        'current_phase_total' => 1,
    ]);

    $job = new ConsolidateEntities($book, $preparation);
    $job->handle();

    $preparation->refresh();
    expect($preparation->current_phase_progress)->toBe(1)
        ->and($preparation->phase_errors)->toHaveCount(1)
        ->and($preparation->phase_errors[0]['phase'])->toBe('entity_extraction')
        ->and($preparation->phase_errors[0]['error'])->toContain('Consolidation:');
});

test('keeps longer description when merging', function () {
    $book = Book::factory()->withAi()->create();

    $canonical = Character::factory()->aiExtracted()->for($book)->create([
        'name' => 'Maja Paulsen',
        'description' => 'Short.',
    ]);

    $duplicate = Character::factory()->aiExtracted()->for($book)->create([
        'name' => 'Paulsen',
        'description' => 'A much longer and more detailed description of this character who appears throughout the story.',
    ]);

    EntityConsolidator::fake(function () use ($canonical, $duplicate) {
        return [
            'character_merges' => [
                [
                    'canonical_id' => $canonical->id,
                    'duplicate_ids' => [$duplicate->id],
                    'canonical_name' => 'Maja Paulsen',
                    'merged_aliases' => ['Paulsen'],
                ],
            ],
            'entity_merges' => [],
        ];
    });

    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'running',
        'current_phase_progress' => 0,
        'current_phase_total' => 1,
    ]);

    $job = new ConsolidateEntities($book, $preparation);
    $job->handle();

    $canonical->refresh();
    expect($canonical->description)->toBe('A much longer and more detailed description of this character who appears throughout the story.');
});
