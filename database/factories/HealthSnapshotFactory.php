<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\HealthSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthSnapshot>
 */
class HealthSnapshotFactory extends Factory
{
    protected $model = HealthSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'composite_score' => fake()->numberBetween(40, 90),
            'hooks_score' => fake()->numberBetween(30, 100),
            'pacing_score' => fake()->numberBetween(30, 100),
            'tension_score' => fake()->numberBetween(30, 100),
            'weave_score' => fake()->numberBetween(30, 100),
            'scene_purpose_score' => fake()->numberBetween(30, 100),
            'tension_dynamics_score' => fake()->numberBetween(30, 100),
            'emotional_arc_score' => fake()->numberBetween(30, 100),
            'craft_score' => fake()->numberBetween(30, 100),
            'recorded_at' => fake()->date(),
        ];
    }
}
