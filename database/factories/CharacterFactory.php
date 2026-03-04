<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Character;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Character>
 */
class CharacterFactory extends Factory
{
    protected $model = Character::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'name' => fake()->firstName() . ' ' . fake()->lastName(),
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

    public function withAliases(): static
    {
        return $this->state(fn (array $attributes) => [
            'aliases' => [fake()->firstName(), fake()->firstName()],
        ]);
    }
}
