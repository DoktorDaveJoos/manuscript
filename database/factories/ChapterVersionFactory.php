<?php

namespace Database\Factories;

use App\Enums\VersionSource;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChapterVersion>
 */
class ChapterVersionFactory extends Factory
{
    protected $model = ChapterVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chapter_id' => Chapter::factory(),
            'version_number' => 1,
            'content' => fake()->paragraphs(5, true),
            'source' => VersionSource::Original,
            'is_current' => true,
        ];
    }

    public function aiRevision(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => VersionSource::AiRevision,
            'change_summary' => fake()->sentence(),
        ]);
    }

    public function manualEdit(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => VersionSource::ManualEdit,
            'change_summary' => fake()->sentence(),
        ]);
    }
}
