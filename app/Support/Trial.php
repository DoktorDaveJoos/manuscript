<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Carbon;

/**
 * One-shot 7-day trial, anchored to a single `trial_started_at` timestamp in
 * the app_settings key-value store. Once the timestamp exists the trial can
 * never be restarted — after the window closes, RequiresLicense applies fully.
 */
class Trial
{
    public const DURATION_DAYS = 7;

    private const STARTED_AT_KEY = 'trial_started_at';

    public static function start(): void
    {
        if (self::hasStarted()) {
            return;
        }

        AppSetting::set(self::STARTED_AT_KEY, now()->toIso8601String());
    }

    public static function hasStarted(): bool
    {
        return self::startedAt() !== null;
    }

    public static function isActive(): bool
    {
        $endsAt = self::endsAt();

        return $endsAt !== null && now()->lt($endsAt);
    }

    public static function hasExpired(): bool
    {
        return self::hasStarted() && ! self::isActive();
    }

    public static function daysRemaining(): int
    {
        $endsAt = self::endsAt();

        if ($endsAt === null || now()->gte($endsAt)) {
            return 0;
        }

        return (int) ceil(now()->diffInDays($endsAt));
    }

    public static function endsAt(): ?Carbon
    {
        return self::startedAt()?->addDays(self::DURATION_DAYS);
    }

    private static function startedAt(): ?Carbon
    {
        $value = AppSetting::get(self::STARTED_AT_KEY);

        return $value !== null ? Carbon::parse($value) : null;
    }
}
