<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class LemonSqueezyService
{
    private const BASE_URL = 'https://api.lemonsqueezy.com/v1/licenses';

    /**
     * @return array{success: bool, data: array<string, mixed>}
     *
     * @throws ConnectionException
     */
    public function activate(string $licenseKey, string $instanceName): array
    {
        $response = Http::asForm()->timeout(10)->post(self::BASE_URL.'/activate', [
            'license_key' => $licenseKey,
            'instance_name' => $instanceName,
        ]);

        return [
            'success' => $response->successful() && ($response->json('activated') === true),
            'data' => $response->json() ?? [],
        ];
    }

    /**
     * @return array{success: bool, data: array<string, mixed>}
     *
     * @throws ConnectionException
     */
    public function validate(string $licenseKey, string $instanceId): array
    {
        $response = Http::asForm()->timeout(10)->post(self::BASE_URL.'/validate', [
            'license_key' => $licenseKey,
            'instance_id' => $instanceId,
        ]);

        return [
            'success' => $response->successful() && ($response->json('valid') === true),
            'data' => $response->json() ?? [],
        ];
    }

    /**
     * @return array{success: bool, data: array<string, mixed>}
     *
     * @throws ConnectionException
     */
    public function deactivate(string $licenseKey, string $instanceId): array
    {
        $response = Http::asForm()->timeout(10)->post(self::BASE_URL.'/deactivate', [
            'license_key' => $licenseKey,
            'instance_id' => $instanceId,
        ]);

        return [
            'success' => $response->successful() && ($response->json('deactivated') === true),
            'data' => $response->json() ?? [],
        ];
    }
}
