<?php

namespace Database\Factories;

use App\Enums\ConnectionType;
use App\Models\Book;
use App\Models\PlotPoint;
use App\Models\PlotPointConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlotPointConnection>
 */
class PlotPointConnectionFactory extends Factory
{
    protected $model = PlotPointConnection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $book = Book::factory();

        return [
            'book_id' => $book,
            'source_plot_point_id' => PlotPoint::factory()->state(['book_id' => $book]),
            'target_plot_point_id' => PlotPoint::factory()->state(['book_id' => $book]),
            'type' => ConnectionType::Causes,
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function setsUp(): static
    {
        return $this->state(fn () => ['type' => ConnectionType::SetsUp]);
    }

    public function resolves(): static
    {
        return $this->state(fn () => ['type' => ConnectionType::Resolves]);
    }

    public function contradicts(): static
    {
        return $this->state(fn () => ['type' => ConnectionType::Contradicts]);
    }
}
