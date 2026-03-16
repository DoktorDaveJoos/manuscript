<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Enums\Genre;
use App\Models\AiSetting;
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
        ];
    }

    /**
     * @param  array<int, Genre>  $secondary
     */
    public function withGenre(Genre $genre, array $secondary = []): static
    {
        return $this->state([
            'genre' => $genre,
            'secondary_genres' => array_map(fn (Genre $g) => $g->value, $secondary),
        ]);
    }

    public function withAi(?AiProvider $provider = null): static
    {
        $provider ??= AiProvider::Anthropic;

        return $this->afterCreating(function (Book $book) use ($provider) {
            AiSetting::factory()->create([
                'provider' => $provider,
            ]);
        });
    }
}
