<?php

use App\Ai\Tools\Plot\ApplyPlotCoachBatch;
use App\Enums\PlotCoachProposalKind;
use App\Models\Book;
use App\Models\Character;
use App\Models\PlotCoachBatch;
use App\Models\PlotCoachProposal;
use App\Models\PlotCoachSession;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;

it('invokes the batch service and returns a confirmation string', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $tool = new ApplyPlotCoachBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Add Mara',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => 'Mara']],
        ],
    ]));

    expect($result)
        ->toContain('Applied batch #')
        ->toContain('Add Mara')
        ->toContain('1 item written.');

    expect(Character::query()->where('book_id', $book->id)->count())->toBe(1);
    expect(PlotCoachBatch::query()->where('session_id', $session->id)->count())->toBe(1);
});

it('returns an explicit parse error when writes is a malformed JSON string', function () {
    $book = Book::factory()->create();
    PlotCoachSession::factory()->for($book, 'book')->create();

    $malformed = '[{"type":"beat","data":{"title":"Signal","description":"He says: **"hello."**"}}]';

    $result = (string) (new ApplyPlotCoachBatch)->handle(new Request([
        'book_id' => $book->id,
        'summary' => 'malformed',
        'writes' => $malformed,
    ]));

    expect($result)
        ->toContain('Batch failed')
        ->toContain('writes')
        ->toContain('JSON')
        ->toContain('proposal_id');

    expect(PlotCoachBatch::query()->count())->toBe(0);
});

it('returns a failure message when the batch throws', function () {
    $book = Book::factory()->create();
    PlotCoachSession::factory()->for($book, 'book')->create();

    $tool = new ApplyPlotCoachBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'Bad batch',
        'writes' => [
            // Missing act_id — must roll back.
            ['type' => 'plot_point', 'data' => ['title' => 'Orphan']],
        ],
    ]));

    expect($result)->toContain('Batch failed:');
    expect($result)->toContain('Nothing persisted');
    expect(PlotCoachBatch::query()->count())->toBe(0);
});

it('applies a batch via proposal_id (looking writes up from the proposal table)', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $proposal = PlotCoachProposal::create([
        'public_id' => (string) Str::uuid(),
        'session_id' => $session->id,
        'kind' => PlotCoachProposalKind::Batch,
        'writes' => [['type' => 'character', 'data' => ['name' => 'Mara']]],
        'summary' => 'Save Mara',
    ]);

    $tool = new ApplyPlotCoachBatch;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'proposal_id' => $proposal->public_id,
    ]));

    expect($result)->toContain('Applied batch #')->toContain('Save Mara');
    expect(Character::query()->where('book_id', $book->id)->where('name', 'Mara')->exists())->toBeTrue();

    $proposal->refresh();
    expect($proposal->approved_at)->not->toBeNull();
    expect($proposal->applied_batch_id)->not->toBeNull();
});

it('does not use the caller-supplied writes when proposal_id is set — writes come from the persisted proposal', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $proposal = PlotCoachProposal::create([
        'public_id' => (string) Str::uuid(),
        'session_id' => $session->id,
        'kind' => PlotCoachProposalKind::Batch,
        'writes' => [['type' => 'character', 'data' => ['name' => 'RealFromProposal']]],
        'summary' => 'Persisted writes',
    ]);

    $tool = new ApplyPlotCoachBatch;
    $tool->handle(new Request([
        'book_id' => $book->id,
        'proposal_id' => $proposal->public_id,
        // Agent tries to smuggle an extra write — must be ignored.
        'writes' => json_encode([
            ['type' => 'character', 'data' => ['name' => 'HallucinatedExtra']],
        ]),
        'summary' => 'Hallucinated summary',
    ]));

    expect(Character::query()->where('book_id', $book->id)->pluck('name')->all())
        ->toBe(['RealFromProposal']);
});

it('refuses to apply an unknown proposal_id', function () {
    $book = Book::factory()->create();
    PlotCoachSession::factory()->for($book, 'book')->create();

    $tool = new ApplyPlotCoachBatch;
    $result = (string) $tool->handle(new Request([
        'book_id' => $book->id,
        'proposal_id' => (string) Str::uuid(),
    ]));

    expect($result)->toContain('does not exist');
    expect(Character::query()->count())->toBe(0);
});

it('is idempotent when re-applying an already-approved proposal', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $proposal = PlotCoachProposal::create([
        'public_id' => (string) Str::uuid(),
        'session_id' => $session->id,
        'kind' => PlotCoachProposalKind::Batch,
        'writes' => [['type' => 'character', 'data' => ['name' => 'Ivo']]],
        'summary' => 'Save Ivo',
    ]);

    $tool = new ApplyPlotCoachBatch;

    $tool->handle(new Request(['book_id' => $book->id, 'proposal_id' => $proposal->public_id]));
    $second = (string) $tool->handle(new Request(['book_id' => $book->id, 'proposal_id' => $proposal->public_id]));

    expect($second)->toContain('already applied');
    expect(Character::query()->where('book_id', $book->id)->count())->toBe(1);
});

it('accepts book_id as a numeric string', function () {
    $book = Book::factory()->create();
    PlotCoachSession::factory()->for($book, 'book')->create();

    $tool = new ApplyPlotCoachBatch;
    $result = (string) $tool->handle(new Request([
        'book_id' => (string) $book->id,
        'summary' => 'String coerced',
        'writes' => [['type' => 'character', 'data' => ['name' => 'Stringly']]],
    ]));

    expect($result)->toContain('Applied batch #');
});

it('returns a failure message when no active session exists for the book', function () {
    $book = Book::factory()->create();

    $tool = new ApplyPlotCoachBatch($book->id);
    $result = (string) $tool->handle(new Request([
        'summary' => 'No session',
        'writes' => [['type' => 'character', 'data' => ['name' => 'Mara']]],
    ]));

    expect($result)->toContain('no active plot coach session');
});
