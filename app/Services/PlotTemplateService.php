<?php

namespace App\Services;

use App\Enums\PlotPointStatus;
use App\Enums\PlotPointType;
use App\Models\Book;
use Illuminate\Support\Facades\DB;

class PlotTemplateService
{
    /**
     * @return array<string, array{name: string, description: string, acts: array<int, array{title: string, color: string, beats: array<int, array{title: string, type: PlotPointType}>}>}>
     */
    public function getTemplates(): array
    {
        return [
            'three_act' => [
                'name' => 'Three-Act Structure',
                'description' => 'Setup → Confrontation → Resolution. The most universal storytelling framework.',
                'acts' => [
                    [
                        'title' => 'The Setup',
                        'color' => '#B87333',
                        'beats' => [
                            ['title' => 'Opening Image', 'type' => PlotPointType::Setup],
                            ['title' => 'Inciting Incident', 'type' => PlotPointType::Conflict],
                            ['title' => 'Into Turning Point', 'type' => PlotPointType::TurningPoint],
                        ],
                    ],
                    [
                        'title' => 'The Confrontation',
                        'color' => '#8B6914',
                        'beats' => [
                            ['title' => 'Rising Action', 'type' => PlotPointType::Conflict],
                            ['title' => 'Midpoint', 'type' => PlotPointType::TurningPoint],
                            ['title' => 'Crisis', 'type' => PlotPointType::Conflict],
                        ],
                    ],
                    [
                        'title' => 'The Resolution',
                        'color' => '#6B4423',
                        'beats' => [
                            ['title' => 'Climax', 'type' => PlotPointType::TurningPoint],
                            ['title' => 'Final Image', 'type' => PlotPointType::Resolution],
                        ],
                    ],
                ],
            ],
            'five_act' => [
                'name' => 'Five-Act Structure',
                'description' => 'The classic dramatic structure: exposition, rising action, climax, falling action, resolution.',
                'acts' => [
                    [
                        'title' => 'Exposition',
                        'color' => '#B87333',
                        'beats' => [
                            ['title' => 'Hook', 'type' => PlotPointType::Setup],
                            ['title' => 'Inciting Incident', 'type' => PlotPointType::Conflict],
                            ['title' => 'Key Event', 'type' => PlotPointType::TurningPoint],
                        ],
                    ],
                    [
                        'title' => 'Rising Action',
                        'color' => '#8B6914',
                        'beats' => [
                            ['title' => 'First Pinch Point', 'type' => PlotPointType::Conflict],
                            ['title' => 'Midpoint', 'type' => PlotPointType::TurningPoint],
                        ],
                    ],
                    [
                        'title' => 'Climax',
                        'color' => '#A0522D',
                        'beats' => [
                            ['title' => 'Second Pinch Point', 'type' => PlotPointType::Conflict],
                            ['title' => 'Crisis', 'type' => PlotPointType::Conflict],
                            ['title' => 'Climactic Moment', 'type' => PlotPointType::TurningPoint],
                        ],
                    ],
                    [
                        'title' => 'Falling Action',
                        'color' => '#6B4423',
                        'beats' => [
                            ['title' => 'Third Plot Point', 'type' => PlotPointType::TurningPoint],
                            ['title' => 'Consequences', 'type' => PlotPointType::Resolution],
                        ],
                    ],
                    [
                        'title' => 'Resolution',
                        'color' => '#4A3728',
                        'beats' => [
                            ['title' => 'Denouement', 'type' => PlotPointType::Resolution],
                            ['title' => 'Final Image', 'type' => PlotPointType::Resolution],
                        ],
                    ],
                ],
            ],
            'heros_journey' => [
                'name' => "Hero's Journey",
                'description' => 'The mythic quest in 12 stages across three acts. Perfect for fantasy, adventure, and coming-of-age.',
                'acts' => [
                    [
                        'title' => 'Departure',
                        'color' => '#B87333',
                        'beats' => [
                            ['title' => 'Ordinary World', 'type' => PlotPointType::Setup],
                            ['title' => 'Call to Adventure', 'type' => PlotPointType::Conflict],
                            ['title' => 'Refusal of the Call', 'type' => PlotPointType::Conflict],
                            ['title' => 'Meeting the Mentor', 'type' => PlotPointType::Setup],
                        ],
                    ],
                    [
                        'title' => 'Initiation',
                        'color' => '#8B6914',
                        'beats' => [
                            ['title' => 'Crossing the Threshold', 'type' => PlotPointType::TurningPoint],
                            ['title' => 'Tests, Allies, Enemies', 'type' => PlotPointType::Conflict],
                            ['title' => 'The Ordeal', 'type' => PlotPointType::Conflict],
                            ['title' => 'The Reward', 'type' => PlotPointType::TurningPoint],
                            ['title' => 'The Road Back', 'type' => PlotPointType::Conflict],
                        ],
                    ],
                    [
                        'title' => 'Return',
                        'color' => '#6B4423',
                        'beats' => [
                            ['title' => 'The Resurrection', 'type' => PlotPointType::TurningPoint],
                            ['title' => 'Return with the Elixir', 'type' => PlotPointType::Resolution],
                            ['title' => 'The New Normal', 'type' => PlotPointType::Resolution],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array{title: string, color: string, beats: array<int, array{title: string, type: string}>}>  $acts
     * @param  array<string, array<int, int>>|null  $chapterAssignments
     */
    public function createStructure(Book $book, array $acts, ?array $chapterAssignments = null): void
    {
        DB::transaction(function () use ($book, $acts, $chapterAssignments) {
            $book->acts()->delete();

            $plotPointOrder = 0;

            foreach ($acts as $index => $actData) {
                $act = $book->acts()->create([
                    'number' => $index + 1,
                    'title' => $actData['title'],
                    'color' => $actData['color'] ?? null,
                    'sort_order' => $index,
                ]);

                foreach ($actData['beats'] as $beat) {
                    $book->plotPoints()->create([
                        'act_id' => $act->id,
                        'title' => $beat['title'],
                        'type' => $beat['type'],
                        'status' => PlotPointStatus::Planned,
                        'sort_order' => $plotPointOrder++,
                        'is_ai_derived' => false,
                    ]);
                }

                if ($chapterAssignments !== null) {
                    $key = "act_index_{$index}";
                    if (isset($chapterAssignments[$key])) {
                        $book->chapters()
                            ->whereIn('id', $chapterAssignments[$key])
                            ->update(['act_id' => $act->id]);
                    }
                }
            }
        });
    }
}
