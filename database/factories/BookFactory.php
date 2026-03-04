<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'language' => 'de',
            'ai_provider' => AiProvider::Anthropic,
            'ai_enabled' => false,
        ];
    }

    public function withAi(): static
    {
        return $this->state(fn (array $attributes) => [
            'ai_enabled' => true,
            'api_key' => 'sk-test-' . fake()->sha256(),
        ]);
    }
}
