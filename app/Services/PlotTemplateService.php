<?php

namespace App\Services;

use App\Enums\PlotPointStatus;
use App\Models\Book;
use Illuminate\Support\Facades\DB;

class PlotTemplateService
{
    /**
     * @param  array<int, array{title: string, color: string, beats: array<int, array{title: string, type: string}>}>  $acts
     * @param  array<string, array<int, int>>|null  $chapterAssignments
     */
    public function createStructure(Book $book, array $acts, ?array $chapterAssignments = null): void
    {
        DB::transaction(function () use ($book, $acts, $chapterAssignments) {
            $book->acts()->delete();

            $plotPointOrder = 0;
            $allBeats = [];

            foreach ($acts as $index => $actData) {
                $act = $book->acts()->create([
                    'number' => $index + 1,
                    'title' => $actData['title'],
                    'color' => $actData['color'] ?? null,
                    'sort_order' => $index,
                ]);

                foreach ($actData['beats'] as $beat) {
                    $allBeats[] = [
                        'book_id' => $book->id,
                        'act_id' => $act->id,
                        'title' => $beat['title'],
                        'type' => $beat['type'],
                        'status' => PlotPointStatus::Planned,
                        'sort_order' => $plotPointOrder++,
                        'is_ai_derived' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
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

            if ($allBeats !== []) {
                $book->plotPoints()->insert($allBeats);
            }
        });
    }
}
