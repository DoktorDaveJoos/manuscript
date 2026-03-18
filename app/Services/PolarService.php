<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PolarService
{
    private const BASE_URL = 'https://api.polar.sh/v1/license-keys';

    private const SANDBOX_BASE_URL = 'https://sandbox-api.polar.sh/v1/license-keys';

    private function request(): PendingRequest
    {
        $baseUrl = app()->environment('local') ? self::SANDBOX_BASE_URL : self::BASE_URL;

        return Http::asJson()
            ->timeout(10)
            ->baseUrl($baseUrl)
            ->withToken(config('app.polar.access_token'));
    }

    /**
     * @return array{success: bool, data: array<string, mixed>}
     *
     * @throws ConnectionException
     */
    public function activate(string $key, string $label): array
    {
        $response = $this->request()->post('/activate', [
            'key' => $key,
            'organization_id' => config('app.polar.organization_id'),
            'label' => $label,
        ]);

        $data = $response->json() ?? [];

        if ($response->status() === 401) {
            return [
                'success' => false,
                'data' => ['detail' => __('License server authentication failed. Please contact support.')],
            ];
        }

        $licenseKey = $data['license_key'] ?? [];

        return [
            'success' => $response->successful() && ($licenseKey['status'] ?? null) === 'granted',
            'data' => $data,
        ];
    }

    /**
     * @return array{success: bool, data: array<string, mixed>}
     *
     * @throws ConnectionException
     */
    public function validate(string $key, string $activationId): array
    {
        $response = $this->request()->post('/validate', [
            'key' => $key,
            'organization_id' => config('app.polar.organization_id'),
            'activation_id' => $activationId,
        ]);

        $data = $response->json() ?? [];

        return [
            'success' => $response->successful() && ($data['status'] ?? null) === 'granted',
            'data' => $data,
        ];
    }

    /**
     * @return array{success: bool, data: array<string, mixed>}
     *
     * @throws ConnectionException
     */
    public function deactivate(string $key, string $activationId): array
    {
        $response = $this->request()->post('/deactivate', [
            'key' => $key,
            'organization_id' => config('app.polar.organization_id'),
            'activation_id' => $activationId,
        ]);

        return [
            'success' => $response->status() === 204,
            'data' => [],
        ];
    }
}
