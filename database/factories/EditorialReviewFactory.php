<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\EditorialReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditorialReview>
 */
class EditorialReviewFactory extends Factory
{
    protected $model = EditorialReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'status' => 'completed',
            'overall_score' => fake()->numberBetween(40, 95),
            'executive_summary' => fake()->paragraphs(2, true),
            'top_strengths' => [fake()->sentence(), fake()->sentence(), fake()->sentence()],
            'top_improvements' => [fake()->sentence(), fake()->sentence(), fake()->sentence()],
            'started_at' => now()->subMinutes(15),
            'completed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => 'pending',
            'overall_score' => null,
            'executive_summary' => null,
            'top_strengths' => null,
            'top_improvements' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error_message' => 'AI provider returned an error.',
            'overall_score' => null,
            'executive_summary' => null,
            'top_strengths' => null,
            'top_improvements' => null,
            'started_at' => now()->subMinutes(5),
            'completed_at' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state([
            'status' => 'analyzing',
            'progress' => ['phase' => 'analyzing', 'current_chapter' => 3, 'total_chapters' => 12],
            'overall_score' => null,
            'executive_summary' => null,
            'top_strengths' => null,
            'top_improvements' => null,
            'started_at' => now()->subMinutes(5),
            'completed_at' => null,
        ]);
    }
}
