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
                'chapter_note' => fake()->paragraph(),
            ],
        ];
    }
}
