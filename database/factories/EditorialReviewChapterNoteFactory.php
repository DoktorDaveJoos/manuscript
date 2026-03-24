<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\EditorialReview;
use App\Models\EditorialReviewChapterNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditorialReviewChapterNote>
 */
class EditorialReviewChapterNoteFactory extends Factory
{
    protected $model = EditorialReviewChapterNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'editorial_review_id' => EditorialReview::factory(),
            'chapter_id' => Chapter::factory(),
            'notes' => [
                'narrative_voice' => ['observations' => [fake()->sentence()]],
                'themes' => ['motifs' => [fake()->word()]],
                'scene_craft' => ['scene_purposes' => ['setup']],
                'prose_style_patterns' => ['repetitions' => [fake()->sentence()]],
            ],
        ];
    }
}
