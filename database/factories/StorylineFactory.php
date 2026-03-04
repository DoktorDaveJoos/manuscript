<?php

namespace Database\Factories;

use App\Enums\StorylineType;
use App\Models\Book;
use App\Models\Storyline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Storyline>
 */
class StorylineFactory extends Factory
{
    protected $model = Storyline::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'name' => fake()->words(2, true),
            'type' => StorylineType::Main,
            'color' => fake()->hexColor(),
            'sort_order' => 0,
        ];
    }

    public function backstory(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => StorylineType::Backstory,
        ]);
    }

    public function parallel(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => StorylineType::Parallel,
        ]);
    }
}
