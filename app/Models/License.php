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
     * Validate a license key using Ed25519 signature verification.
     *
     * Format: MANU.{ID:8hex}.{SIGNATURE:base64url}
     */
    public static function validate(string $key): bool
    {
        if (! extension_loaded('sodium')) {
            return false;
        }

        $parts = explode('.', $key, 3);

        if (count($parts) !== 3 || $parts[0] !== 'MANU') {
            return false;
        }

        $id = $parts[1];
        $signatureB64 = $parts[2];

        if (! preg_match('/^[A-F0-9]{8}$/', $id)) {
            return false;
        }

        $signature = base64_decode(strtr($signatureB64, '-_', '+/'), strict: true);

        if ($signature === false || strlen($signature) !== \SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        /** @var string $publicKeyB64 */
        $publicKeyB64 = config('app.license_public_key');
        $publicKey = base64_decode($publicKeyB64, strict: true);

        if ($publicKey === false) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $id, $publicKey);
    }
}
