<?php

namespace Database\Factories;

use App\Models\Act;
use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Act>
 */
class ActFactory extends Factory
{
    protected $model = Act::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'number' => fake()->numberBetween(1, 5),
            'title' => fake()->sentence(2),
            'description' => fake()->paragraph(),
            'color' => fake()->hexColor(),
            'sort_order' => 0,
        ];
    }
}
