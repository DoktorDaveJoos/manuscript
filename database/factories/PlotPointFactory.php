<?php

namespace Database\Factories;

use App\Enums\PlotPointStatus;
use App\Enums\PlotPointType;
use App\Models\Book;
use App\Models\PlotPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlotPoint>
 */
class PlotPointFactory extends Factory
{
    protected $model = PlotPoint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'type' => PlotPointType::Setup,
            'status' => PlotPointStatus::Planned,
            'sort_order' => 0,
        ];
    }

    public function fulfilled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PlotPointStatus::Fulfilled,
        ]);
    }

    public function conflict(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PlotPointType::Conflict,
        ]);
    }
}
