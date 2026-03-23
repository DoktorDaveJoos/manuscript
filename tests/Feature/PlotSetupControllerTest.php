<?php

use App\Enums\PlotPointStatus;
use App\Enums\PlotPointType;
use App\Models\Act;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Models\Storyline;

beforeEach(fn () => License::factory()->create());

test('it creates three-act structure with acts and plot points', function () {
    $book = Book::factory()->create();

    $response = $this->post("/books/{$book->id}/plot/setup-structure", [
        'template' => 'three_act',
        'acts' => [
            [
                'title' => 'The Setup',
                'color' => '#B87333',
                'beats' => [
                    ['title' => 'Opening Image', 'type' => 'setup'],
                    ['title' => 'Inciting Incident', 'type' => 'conflict'],
                ],
            ],
            [
                'title' => 'The Confrontation',
                'color' => '#8B6914',
                'beats' => [
                    ['title' => 'Midpoint', 'type' => 'turning_point'],
                ],
            ],
            [
                'title' => 'The Resolution',
                'color' => '#6B4423',
                'beats' => [
                    ['title' => 'Climax', 'type' => 'turning_point'],
                    ['title' => 'Final Image', 'type' => 'resolution'],
                ],
            ],
        ],
    ]);

    $response->assertRedirect("/books/{$book->id}/plot");

    expect($book->acts()->count())->toBe(3)
        ->and($book->plotPoints()->count())->toBe(5);

    $firstAct = $book->acts()->where('number', 1)->first();
    expect($firstAct->title)->toBe('The Setup')
        ->and($firstAct->color)->toBe('#B87333')
        ->and($firstAct->sort_order)->toBe(0);

    $beats = $book->plotPoints()->where('act_id', $firstAct->id)->orderBy('sort_order')->get();
    expect($beats)->toHaveCount(2)
        ->and($beats[0]->title)->toBe('Opening Image')
        ->and($beats[0]->type)->toBe(PlotPointType::Setup)
        ->and($beats[0]->status)->toBe(PlotPointStatus::Planned)
        ->and($beats[1]->title)->toBe('Inciting Incident')
        ->and($beats[1]->type)->toBe(PlotPointType::Conflict);
});

test('it creates five-act structure', function () {
    $book = Book::factory()->create();

    $this->post("/books/{$book->id}/plot/setup-structure", [
        'template' => 'five_act',
        'acts' => [
            ['title' => 'Exposition', 'color' => '#B87333', 'beats' => [['title' => 'Hook', 'type' => 'setup']]],
            ['title' => 'Rising Action', 'color' => '#8B6914', 'beats' => [['title' => 'Midpoint', 'type' => 'turning_point']]],
            ['title' => 'Climax', 'color' => '#A0522D', 'beats' => [['title' => 'Crisis', 'type' => 'conflict']]],
            ['title' => 'Falling Action', 'color' => '#6B4423', 'beats' => [['title' => 'Consequences', 'type' => 'resolution']]],
            ['title' => 'Resolution', 'color' => '#4A3728', 'beats' => [['title' => 'Final Image', 'type' => 'resolution']]],
        ],
    ])->assertRedirect();

    expect($book->acts()->count())->toBe(5)
        ->and($book->plotPoints()->count())->toBe(5);
});

test('it assigns chapters to acts when chapter_assignments provided', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $ch1 = Chapter::factory()->for($book)->for($storyline)->create();
    $ch2 = Chapter::factory()->for($book)->for($storyline)->create();
    $ch3 = Chapter::factory()->for($book)->for($storyline)->create();

    $this->post("/books/{$book->id}/plot/setup-structure", [
        'template' => 'three_act',
        'acts' => [
            ['title' => 'Act 1', 'color' => '#B87333', 'beats' => [['title' => 'Beat 1', 'type' => 'setup']]],
            ['title' => 'Act 2', 'color' => '#8B6914', 'beats' => [['title' => 'Beat 2', 'type' => 'conflict']]],
        ],
        'chapter_assignments' => [
            'act_index_0' => [$ch1->id, $ch2->id],
            'act_index_1' => [$ch3->id],
        ],
    ])->assertRedirect();

    $act1 = $book->acts()->where('number', 1)->first();
    $act2 = $book->acts()->where('number', 2)->first();

    expect($ch1->fresh()->act_id)->toBe($act1->id)
        ->and($ch2->fresh()->act_id)->toBe($act1->id)
        ->and($ch3->fresh()->act_id)->toBe($act2->id);
});

test('it replaces existing acts when re-running wizard', function () {
    $book = Book::factory()->create();
    Act::factory()->for($book)->create(['number' => 1, 'title' => 'Old Act']);

    expect($book->acts()->count())->toBe(1);

    $this->post("/books/{$book->id}/plot/setup-structure", [
        'template' => 'three_act',
        'acts' => [
            ['title' => 'New Act 1', 'color' => '#B87333', 'beats' => [['title' => 'Beat', 'type' => 'setup']]],
            ['title' => 'New Act 2', 'color' => '#8B6914', 'beats' => [['title' => 'Beat', 'type' => 'conflict']]],
        ],
    ])->assertRedirect();

    expect($book->acts()->count())->toBe(2)
        ->and($book->acts()->where('title', 'Old Act')->exists())->toBeFalse();
});

test('it validates required fields', function () {
    $book = Book::factory()->create();

    $this->post("/books/{$book->id}/plot/setup-structure", [])
        ->assertSessionHasErrors(['template', 'acts']);
});

test('it validates template must be a known key', function () {
    $book = Book::factory()->create();

    $this->post("/books/{$book->id}/plot/setup-structure", [
        'template' => 'unknown_template',
        'acts' => [
            ['title' => 'Act', 'color' => '#000', 'beats' => [['title' => 'Beat', 'type' => 'setup']]],
        ],
    ])->assertSessionHasErrors(['template']);
});

test('it validates beat type must be a valid enum', function () {
    $book = Book::factory()->create();

    $this->post("/books/{$book->id}/plot/setup-structure", [
        'template' => 'three_act',
        'acts' => [
            ['title' => 'Act', 'color' => '#000', 'beats' => [['title' => 'Beat', 'type' => 'invalid_type']]],
        ],
    ])->assertSessionHasErrors(['acts.0.beats.0.type']);
});

test('it creates save-the-cat structure with 3 acts and 15 beats', function () {
    $book = Book::factory()->create();

    $this->post("/books/{$book->id}/plot/setup-structure", [
        'template' => 'save_the_cat',
        'acts' => [
            [
                'title' => 'Setup',
                'color' => '#B87333',
                'beats' => [
                    ['title' => 'Opening Image', 'type' => 'setup'],
                    ['title' => 'Theme Stated', 'type' => 'setup'],
                    ['title' => 'Set-Up', 'type' => 'setup'],
                    ['title' => 'Catalyst', 'type' => 'conflict'],
                    ['title' => 'Debate', 'type' => 'conflict'],
                ],
            ],
            [
                'title' => 'Confrontation',
                'color' => '#8B6914',
                'beats' => [
                    ['title' => 'Break Into Two', 'type' => 'turning_point'],
                    ['title' => 'B Story', 'type' => 'setup'],
                    ['title' => 'Fun and Games', 'type' => 'conflict'],
                    ['title' => 'Midpoint', 'type' => 'turning_point'],
                    ['title' => 'Bad Guys Close In', 'type' => 'conflict'],
                    ['title' => 'All Is Lost', 'type' => 'conflict'],
                    ['title' => 'Dark Night of the Soul', 'type' => 'conflict'],
                ],
            ],
            [
                'title' => 'Resolution',
                'color' => '#6B4423',
                'beats' => [
                    ['title' => 'Break Into Three', 'type' => 'turning_point'],
                    ['title' => 'Finale', 'type' => 'resolution'],
                    ['title' => 'Final Image', 'type' => 'resolution'],
                ],
            ],
        ],
    ])->assertRedirect();

    expect($book->acts()->count())->toBe(3)
        ->and($book->plotPoints()->count())->toBe(15);
});

test('it creates story-circle structure with 2 acts and 8 beats', function () {
    $book = Book::factory()->create();

    $this->post("/books/{$book->id}/plot/setup-structure", [
        'template' => 'story_circle',
        'acts' => [
            [
                'title' => 'The Descent',
                'color' => '#B87333',
                'beats' => [
                    ['title' => 'You', 'type' => 'setup'],
                    ['title' => 'Need', 'type' => 'conflict'],
                    ['title' => 'Go', 'type' => 'turning_point'],
                    ['title' => 'Search', 'type' => 'conflict'],
                ],
            ],
            [
                'title' => 'The Return',
                'color' => '#8B6914',
                'beats' => [
                    ['title' => 'Find', 'type' => 'turning_point'],
                    ['title' => 'Take', 'type' => 'conflict'],
                    ['title' => 'Return', 'type' => 'resolution'],
                    ['title' => 'Change', 'type' => 'resolution'],
                ],
            ],
        ],
    ])->assertRedirect();

    expect($book->acts()->count())->toBe(2)
        ->and($book->plotPoints()->count())->toBe(8);
});

test('it sets sort_order sequentially across all acts', function () {
    $book = Book::factory()->create();

    $this->post("/books/{$book->id}/plot/setup-structure", [
        'template' => 'three_act',
        'acts' => [
            ['title' => 'Act 1', 'color' => '#B87333', 'beats' => [
                ['title' => 'Beat 1', 'type' => 'setup'],
                ['title' => 'Beat 2', 'type' => 'conflict'],
            ]],
            ['title' => 'Act 2', 'color' => '#8B6914', 'beats' => [
                ['title' => 'Beat 3', 'type' => 'turning_point'],
            ]],
        ],
    ])->assertRedirect();

    $plotPoints = $book->plotPoints()->orderBy('sort_order')->get();
    expect($plotPoints[0]->sort_order)->toBe(0)
        ->and($plotPoints[1]->sort_order)->toBe(1)
        ->and($plotPoints[2]->sort_order)->toBe(2);
});
