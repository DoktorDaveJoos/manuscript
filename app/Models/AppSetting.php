<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $guarded = [];

    /** @var array<string, mixed> */
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key] ?? $default;
        }

        $setting = self::query()->where('key', $key)->first();

        $value = self::castStoredValue($setting?->value ?? $default);

        self::$cache[$key] = $value;

        return $value;
    }

    /**
     * Pre-load multiple settings into the per-request cache in a single query.
     * Subsequent calls to {@see self::get()} for these keys will hit the cache
     * instead of issuing N separate SELECTs.
     *
     * @param  array<int, string>  $keys
     */
    public static function warmCache(array $keys): void
    {
        $missing = array_values(array_filter(
            $keys,
            fn (string $key): bool => ! array_key_exists($key, self::$cache),
        ));

        if ($missing === []) {
            return;
        }

        $rows = self::query()
            ->whereIn('key', $missing)
            ->pluck('value', 'key');

        foreach ($missing as $key) {
            // Store null for missing keys so subsequent get() calls fall back
            // to the caller-supplied default (via the ?? in get()) without
            // re-querying.
            self::$cache[$key] = self::castStoredValue($rows->get($key));
        }
    }

    /**
     * Stored values are persisted as strings; coerce the documented
     * boolean-string convention back to real booleans.
     */
    private static function castStoredValue(mixed $value): mixed
    {
        return match ($value) {
            'true' => true,
            'false' => false,
            default => $value,
        };
    }

    public static function set(string $key, mixed $value): void
    {
        $storedValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

        self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $storedValue],
        );

        self::$cache[$key] = $value;
    }

    public static function showAiFeatures(): bool
    {
        return (bool) self::get('show_ai_features', true);
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
