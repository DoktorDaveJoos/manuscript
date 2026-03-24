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
            'status' => 'pending',
        ];
    }

    public function analyzing(): static
    {
        return $this->state([
            'status' => 'analyzing',
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'overall_score' => fake()->numberBetween(40, 90),
            'executive_summary' => fake()->paragraphs(2, true),
            'top_strengths' => [fake()->sentence(), fake()->sentence(), fake()->sentence()],
            'top_improvements' => [fake()->sentence(), fake()->sentence(), fake()->sentence()],
            'started_at' => now()->subMinutes(15),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error_message' => 'Something went wrong.',
            'started_at' => now()->subMinutes(5),
        ]);
    }
}
