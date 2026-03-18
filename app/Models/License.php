<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    use HasFactory;

    protected $guarded = [];

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
        return self::query()->where('activated', true)->first();
    }

    /**
     * Check whether a valid license is active.
     */
    public static function isActive(): bool
    {
        return self::active() !== null;
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
