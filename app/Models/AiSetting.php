<?php

namespace App\Models;

use App\Enums\AiProvider;
use App\Enums\AiTaskCategory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Sentry\State\Scope;

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
            'api_key_recovery_needed' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Writing a fresh api_key clears the recovery flag — once the user
        // re-enters their key under the current APP_KEY, the row is healthy
        // again and the UI banner should disappear.
        static::saving(function (self $model): void {
            if ($model->isDirty('api_key') && filled($model->getAttributes()['api_key'] ?? null)) {
                $model->api_key_recovery_needed = false;
            }
        });
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
     * Read the decrypted api_key, tolerating a rotated APP_KEY.
     *
     * Why: NativePHP desktop builds have historically shipped fresh APP_KEYs per
     * release, making previously-encrypted rows undecryptable after auto-update
     * (Sentry issue 112306879). Instead of crashing every Inertia render, we
     * null the stale cipher, flag the row for recovery, and surface the state
     * to the UI so the user can re-enter the key.
     */
    public function decryptedApiKey(): ?string
    {
        if (blank($this->getRawOriginal('api_key'))) {
            return null;
        }

        try {
            return $this->api_key;
        } catch (DecryptException $e) {
            $this->markApiKeyUnrecoverable($e);

            return null;
        }
    }

    /**
     * Null the stale cipher and flag the row so the UI can prompt a re-entry.
     * Uses a direct DB update to bypass the encrypted cast, and guards the
     * Sentry report so we fire once per row instead of once per page render.
     */
    protected function markApiKeyUnrecoverable(\Throwable $e): void
    {
        if ($this->api_key_recovery_needed) {
            return;
        }

        DB::table($this->getTable())
            ->where('id', $this->id)
            ->update([
                'api_key' => null,
                'api_key_recovery_needed' => true,
                'updated_at' => $this->freshTimestamp(),
            ]);

        $this->setRawAttributes(array_merge($this->getAttributes(), [
            'api_key' => null,
            'api_key_recovery_needed' => true,
        ]), true);

        if (app()->bound('sentry')) {
            \Sentry\withScope(function (Scope $scope) use ($e) {
                $scope->setTag('ai_setting.api_key_recovery', 'triggered');
                $scope->setContext('ai_setting', [
                    'id' => $this->id,
                    'provider' => $this->provider?->value,
                ]);
                \Sentry\captureException($e);
            });
        }
    }

    /**
     * Check if this provider has a configured API key.
     */
    public function hasApiKey(): bool
    {
        return filled($this->decryptedApiKey());
    }

    /**
     * Get a partially masked version of the API key for display.
     */
    public function maskedApiKey(): ?string
    {
        $key = $this->decryptedApiKey();

        if (! filled($key)) {
            return null;
        }

        $length = strlen($key);

        if ($length <= 8) {
            return str_repeat('•', $length);
        }

        $visibleEnd = substr($key, -4);
        $prefix = '';

        // Preserve common prefixes like "sk-" so the user recognizes the key
        if (preg_match('/^([a-z]{2,4}[-_])/', $key, $matches)) {
            $prefix = $matches[1];
        }

        return $prefix.'••••••'.$visibleEnd;
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

        $apiKey = $this->decryptedApiKey();
        if ($apiKey) {
            config(["ai.providers.{$key}.key" => $apiKey]);
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
     * @return Collection<int, self>
     */
    public static function enabledProviders(): Collection
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

    /**
     * Serialize this setting for frontend consumption (never exposes the raw API key).
     *
     * @return array<string, mixed>
     */
    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider->value,
            'has_api_key' => $this->hasApiKey(),
            'masked_api_key' => $this->maskedApiKey(),
            'base_url' => $this->base_url,
            'api_version' => $this->api_version,
            'text_model' => $this->text_model,
            'writing_model' => $this->writing_model,
            'analysis_model' => $this->analysis_model,
            'extraction_model' => $this->extraction_model,
            'embedding_model' => $this->embedding_model,
            'embedding_dimensions' => $this->embedding_dimensions,
            'enabled' => $this->enabled,
            'requires_api_key' => $this->provider->requiresApiKey(),
            'requires_base_url' => $this->provider->requiresBaseUrl(),
            'api_key_recovery_needed' => (bool) $this->api_key_recovery_needed,
        ];
    }
}
