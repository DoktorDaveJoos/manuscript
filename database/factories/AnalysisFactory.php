<?php

namespace Database\Factories;

use App\Enums\AnalysisType;
use App\Models\Analysis;
use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Analysis>
 */
class AnalysisFactory extends Factory
{
    protected $model = Analysis::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'type' => AnalysisType::Pacing,
            'result' => ['score' => fake()->numberBetween(1, 10), 'notes' => fake()->sentence()],
            'ai_generated' => false,
        ];
    }
}
