<?php

namespace Database\Factories;

use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseFactory extends Factory
{
    protected $model = License::class;

    public const TEST_SECRET_KEY = '8JYOFnEzMIumHktSSE4j4Qm1QJaQ/CyNY2zu9B9J2lxmpS1FKY8XOoXlM+boGraqWk31KN4wvKW4PXOFRTqaxQ==';

    public const TEST_PUBLIC_KEY = 'ZqUtRSmPFzqF5TPm6Bq2qlpN9SjeMLyluD1zhUU6msU=';

    public function definition(): array
    {
        $id = strtoupper(bin2hex(random_bytes(4)));
        $secretKey = base64_decode(self::TEST_SECRET_KEY);
        $signature = sodium_crypto_sign_detached($id, $secretKey);
        $signatureB64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return [
            'key' => 'MANU.'.$id.'.'.$signatureB64,
            'activated' => true,
        ];
    }

    public function deactivated(): static
    {
        return $this->state(fn () => [
            'activated' => false,
        ]);
    }
}
