<?php

namespace Database\Factories;

use App\Models\DesignTemplate;
use App\Services\Export\Templates\ClassicTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DesignTemplate>
 */
class DesignTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'based_on' => 'classic',
            'settings' => (new ClassicTemplate)->designSettings(),
        ];
    }
}
