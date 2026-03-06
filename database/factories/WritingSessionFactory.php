<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\WritingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WritingSession>
 */
class WritingSessionFactory extends Factory
{
    protected $model = WritingSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'date' => fake()->date(),
            'words_written' => fake()->numberBetween(100, 2000),
            'goal_met' => false,
        ];
    }

    public function goalMet(): static
    {
        return $this->state(fn (array $attributes) => [
            'goal_met' => true,
        ]);
    }
}
