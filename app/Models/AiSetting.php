<?php

namespace App\Models;

use App\Enums\AiProvider;
use App\Enums\AiTaskCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'api_key' => 'encrypted',
            'enabled' => 'boolean',
            'embedding_dimensions' => 'integer',
        ];
    }

    /**
     * Get the setting for a specific provider, creating a disabled default if none exists.
     */
    public static function forProvider(AiProvider $provider): self
    {
        return self::query()->firstOrCreate(
            ['provider' => $provider],
            ['enabled' => false],
        );
    }

    /**
     * Check if this provider has a configured API key.
     */
    public function hasApiKey(): bool
    {
        return filled($this->api_key);
    }

    /**
     * Whether this setting has the credentials needed to make API calls.
     */
    public function isConfigured(): bool
    {
        if ($this->provider->requiresApiKey()) {
            return $this->hasApiKey();
        }

        return filled($this->base_url);
    }

    /**
     * Inject this setting's credentials into the runtime config.
     */
    public function injectConfig(): void
    {
        $key = $this->provider->value;

        config(['ai.default' => $key]);

        if ($this->api_key) {
            config(["ai.providers.{$key}.key" => $this->api_key]);
        }

        if ($this->base_url) {
            config(["ai.providers.{$key}.url" => $this->base_url]);
        }

        if ($this->provider === AiProvider::Azure) {
            if ($this->api_version) {
                config(["ai.providers.{$key}.api_version" => $this->api_version]);
            }

            if ($this->text_model) {
                config(["ai.providers.{$key}.deployment" => $this->text_model]);
            }

            if ($this->embedding_model) {
                config(["ai.providers.{$key}.embedding_deployment" => $this->embedding_model]);
            }
        }
    }

    /**
     * Get all globally enabled providers.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function enabledProviders(): \Illuminate\Database\Eloquent\Collection
    {
        return self::query()->where('enabled', true)->get();
    }

    /**
     * Select a single provider, disabling all others.
     */
    public static function selectProvider(AiProvider $provider): self
    {
        self::query()->where('provider', '!=', $provider)->update(['enabled' => false]);

        $setting = self::forProvider($provider);
        $setting->update(['enabled' => true]);

        return $setting;
    }

    /**
     * Get the currently active (selected) provider, if any.
     */
    public static function activeProvider(): ?self
    {
        return self::query()->where('enabled', true)->first();
    }

    /**
     * Get the user-configured model for a task category, or null to use SDK defaults.
     */
    public function modelForCategory(AiTaskCategory $category): ?string
    {
        return $this->{$category->column()};
    }
}
