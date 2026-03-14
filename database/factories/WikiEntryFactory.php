<?php

namespace Database\Factories;

use App\Enums\WikiEntryKind;
use App\Models\Book;
use App\Models\WikiEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WikiEntry>
 */
class WikiEntryFactory extends Factory
{
    protected $model = WikiEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'kind' => fake()->randomElement(WikiEntryKind::cases()),
            'name' => fake()->words(2, true),
            'description' => fake()->paragraph(),
            'is_ai_extracted' => false,
        ];
    }

    public function aiExtracted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_ai_extracted' => true,
        ]);
    }

    public function location(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => WikiEntryKind::Location,
            'type' => fake()->randomElement(['City', 'Country', 'Building', 'Forest']),
        ]);
    }

    public function organization(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => WikiEntryKind::Organization,
            'type' => fake()->randomElement(['Guild', 'Government', 'Corporation', 'Order']),
        ]);
    }

    public function item(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => WikiEntryKind::Item,
            'type' => fake()->randomElement(['Weapon', 'Artifact', 'Document', 'Tool']),
        ]);
    }

    public function lore(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => WikiEntryKind::Lore,
            'type' => fake()->randomElement(['Legend', 'History', 'Prophecy', 'Custom']),
        ]);
    }
}
