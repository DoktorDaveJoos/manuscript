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
            return self::$cache[$key];
        }

        $setting = self::query()->where('key', $key)->first();

        $value = $setting?->value ?? $default;

        // Cast boolean strings
        if ($value === 'true') {
            $value = true;
        } elseif ($value === 'false') {
            $value = false;
        }

        self::$cache[$key] = $value;

        return $value;
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
