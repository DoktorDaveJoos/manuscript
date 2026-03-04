<?php

namespace Database\Factories;

use App\Enums\ChapterStatus;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chapter>
 */
class ChapterFactory extends Factory
{
    protected $model = Chapter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'storyline_id' => Storyline::factory(),
            'title' => fake()->sentence(3),
            'reader_order' => 0,
            'status' => ChapterStatus::Draft,
            'word_count' => fake()->numberBetween(500, 5000),
        ];
    }

    public function revised(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChapterStatus::Revised,
        ]);
    }

    public function final(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChapterStatus::Final,
        ]);
    }
}
