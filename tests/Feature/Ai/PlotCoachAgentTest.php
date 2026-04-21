<?php

use App\Ai\Agents\PlotCoachAgent;
use App\Ai\Tools\LookupExistingEntities;
use App\Ai\Tools\Plot\GetPlotBoardState;
use App\Ai\Tools\RetrieveManuscriptContext;
use App\Enums\Genre;
use App\Enums\PlotCoachStage;
use App\Models\Book;
use App\Models\PlotCoachSession;

test('plot coach agent registers GetPlotBoardState and related tools', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $agent = new PlotCoachAgent($book, $session);
    $tools = iterator_to_array($agent->tools());

    expect($tools)->toHaveCount(3);

    $toolClasses = array_map(fn ($t) => $t::class, $tools);

    expect($toolClasses)->toContain(GetPlotBoardState::class);
    expect($toolClasses)->toContain(RetrieveManuscriptContext::class);
    expect($toolClasses)->toContain(LookupExistingEntities::class);
});

test('plot coach agent composes intake-stage instructions with session state', function () {
    $book = Book::factory()->create([
        'title' => 'The Copper Hour',
        'genre' => Genre::Thriller,
    ]);

    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'stage' => PlotCoachStage::Intake,
        'decisions' => ['genre' => 'thriller'],
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Intake')
        ->toContain('The Copper Hour')
        ->toContain('thriller')
        ->toContain('## Session state');
});

test('plot coach agent returns empty stage-guidance for non-intake stages', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'stage' => PlotCoachStage::Plotting,
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->not->toContain('We need to pin down')
        ->toContain('editorial plot coach');
});

test('plot coach agent includes static persona in all stages', function () {
    foreach (PlotCoachStage::cases() as $stage) {
        $book = Book::factory()->create();
        $session = PlotCoachSession::factory()->for($book, 'book')->create([
            'stage' => $stage,
        ]);

        $agent = new PlotCoachAgent($book, $session);
        $instructions = (string) $agent->instructions();

        expect($instructions)->toContain('editorial plot coach');
        expect($instructions)->toContain('Voice rules');
        expect($instructions)->toContain('Discipline rules');
    }
});
