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
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'overall_score' => null,
            'executive_summary' => null,
            'top_strengths' => null,
            'top_improvements' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    public function analyzing(): static
    {
        return $this->state(fn () => [
            'status' => 'analyzing',
            'overall_score' => null,
            'executive_summary' => null,
            'top_strengths' => null,
            'top_improvements' => null,
            'started_at' => now(),
            'completed_at' => null,
            'progress' => ['phase' => 'analyzing', 'current_chapter' => 1, 'total_chapters' => 5],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'error_message' => 'An error occurred during analysis.',
            'overall_score' => null,
            'executive_summary' => null,
            'started_at' => now()->subHour(),
            'completed_at' => null,
        ]);
    }
}
