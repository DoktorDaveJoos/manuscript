<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Per-request cache for the active-license lookup. License state rarely
     * changes within a single request, so reusing the result avoids the 3+
     * duplicate queries that previously happened (HandleInertiaRequests::share,
     * RequiresLicense middleware, free_tier closure all hit it).
     *
     * Use the `false` sentinel to distinguish "not yet looked up" from
     * "looked up and the result was null".
     */
    private static self|false|null $activeCache = false;

    protected static function booted(): void
    {
        // Mass deletes via the query builder bypass these — see LicenseController::deactivate.
        static::saved(fn () => self::clearActiveCache());
        static::deleted(fn () => self::clearActiveCache());
    }

    protected function casts(): array
    {
        return [
            'activated' => 'boolean',
            'activation_limit' => 'integer',
            'activation_usage' => 'integer',
            'expires_at' => 'datetime',
            'last_validated_at' => 'datetime',
        ];
    }

    /**
     * Get the active license, if any.
     */
    public static function active(): ?self
    {
        if (self::$activeCache !== false) {
            return self::$activeCache;
        }

        return self::$activeCache = self::query()->where('activated', true)->first();
    }

    /**
     * Check whether a valid license is active.
     */
    public static function isActive(): bool
    {
        return self::active() !== null;
    }

    /**
     * Reset the per-request active-license cache. Call after activate /
     * deactivate / revalidate operations so subsequent reads see fresh state.
     */
    public static function clearActiveCache(): void
    {
        self::$activeCache = false;
    }

    /**
     * Check if the license should be silently re-validated (older than 7 days).
     */
    public function needsRevalidation(): bool
    {
        if ($this->last_validated_at === null) {
            return true;
        }

        return $this->last_validated_at->diffInDays(now()) >= 7;
    }
}
