<?php

use App\Models\PlotCoachBatch;
use App\Models\PlotCoachSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can be created with factory', function () {
    $batch = PlotCoachBatch::factory()->create();

    expect($batch->exists)->toBeTrue();
    expect(PlotCoachBatch::query()->find($batch->id))->not->toBeNull();
});

it('belongs to a session', function () {
    $session = PlotCoachSession::factory()->create();
    $batch = PlotCoachBatch::factory()->for($session, 'session')->create();

    expect($batch->session)->not->toBeNull();
    expect($batch->session->id)->toBe($session->id);
});

it('casts payload to array', function () {
    $payload = ['created' => [['type' => 'character', 'id' => 42]]];

    $batch = PlotCoachBatch::factory()->create([
        'payload' => $payload,
    ]);

    expect($batch->fresh()->payload)->toBe($payload);
});
