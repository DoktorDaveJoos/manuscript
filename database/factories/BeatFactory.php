<?php

namespace Database\Factories;

use App\Enums\BeatStatus;
use App\Models\Beat;
use App\Models\PlotPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Beat>
 */
class BeatFactory extends Factory
{
    protected $model = Beat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plot_point_id' => PlotPoint::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status' => BeatStatus::Planned,
            'sort_order' => 0,
        ];
    }

    public function fulfilled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BeatStatus::Fulfilled,
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BeatStatus::Abandoned,
        ]);
    }
}
