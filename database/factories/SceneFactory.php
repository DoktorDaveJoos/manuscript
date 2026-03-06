<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scene>
 */
class SceneFactory extends Factory
{
    protected $model = Scene::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chapter_id' => Chapter::factory(),
            'title' => 'Scene '.fake()->numberBetween(1, 10),
            'content' => '<p>'.fake()->paragraphs(3, true).'</p>',
            'sort_order' => 0,
            'word_count' => fake()->numberBetween(200, 2000),
        ];
    }
}
