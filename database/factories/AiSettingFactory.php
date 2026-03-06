<?php

namespace Database\Factories;

use App\Enums\AiProvider;
use App\Models\AiSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiSetting>
 */
class AiSettingFactory extends Factory
{
    protected $model = AiSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => AiProvider::Anthropic,
            'api_key' => 'sk-test-'.fake()->sha256(),
            'enabled' => true,
        ];
    }

    public function openai(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => AiProvider::Openai,
        ]);
    }

    public function gemini(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => AiProvider::Gemini,
        ]);
    }

    public function ollama(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => AiProvider::Ollama,
            'base_url' => 'http://localhost:11434',
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    public function withoutKey(): static
    {
        return $this->state(fn (array $attributes) => [
            'api_key' => null,
        ]);
    }
}
