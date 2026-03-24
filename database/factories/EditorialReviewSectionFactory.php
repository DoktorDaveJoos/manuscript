<?php

namespace Database\Factories;

use App\Enums\EditorialSectionType;
use App\Models\EditorialReview;
use App\Models\EditorialReviewSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditorialReviewSection>
 */
class EditorialReviewSectionFactory extends Factory
{
    protected $model = EditorialReviewSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'editorial_review_id' => EditorialReview::factory(),
            'type' => EditorialSectionType::Plot,
            'score' => fake()->numberBetween(40, 95),
            'summary' => fake()->paragraph(),
            'findings' => [
                [
                    'severity' => fake()->randomElement(['critical', 'warning', 'suggestion']),
                    'description' => fake()->sentence(),
                    'chapter_references' => [],
                    'recommendation' => fake()->sentence(),
                ],
            ],
            'recommendations' => [fake()->sentence(), fake()->sentence()],
        ];
    }
}
