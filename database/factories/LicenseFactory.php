<?php

namespace Database\Factories;

use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LicenseFactory extends Factory
{
    protected $model = License::class;

    public function definition(): array
    {
        return [
            'license_key' => fake()->regexify('[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}'),
            'activated' => true,
            'instance_id' => (string) Str::uuid(),
            'instance_name' => fake()->domainWord(),
            'license_key_id' => (string) Str::uuid(),
            'status' => 'granted',
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'product_name' => null,
            'activation_limit' => 5,
            'activation_usage' => 1,
            'expires_at' => null,
            'last_validated_at' => now(),
        ];
    }

    public function deactivated(): static
    {
        return $this->state(fn () => [
            'activated' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);
    }

    public function stale(): static
    {
        return $this->state(fn () => [
            'last_validated_at' => now()->subDays(8),
        ]);
    }
}
