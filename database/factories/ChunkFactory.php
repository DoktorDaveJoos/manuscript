<?php

namespace Database\Factories;

use App\Models\ChapterVersion;
use App\Models\Chunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chunk>
 */
class ChunkFactory extends Factory
{
    protected $model = Chunk::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chapter_version_id' => ChapterVersion::factory(),
            'scene_id' => null,
            'content' => fake()->paragraph(),
            'position' => fake()->numberBetween(0, 100),
        ];
    }
}
