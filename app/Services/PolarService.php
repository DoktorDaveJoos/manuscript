<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Talks to Polar's public customer-portal license-key endpoints. They are
 * scoped by organization_id + key and need no access token, so nothing
 * secret has to ship inside the packaged app.
 */
class PolarService
{
    private const BASE_URL = 'https://api.polar.sh/v1/customer-portal/license-keys';

    private const SANDBOX_BASE_URL = 'https://sandbox-api.polar.sh/v1/customer-portal/license-keys';

    private function request(): PendingRequest
    {
        $baseUrl = app()->environment('local') ? self::SANDBOX_BASE_URL : self::BASE_URL;

        return Http::asJson()
            ->timeout(10)
            ->baseUrl($baseUrl);
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

        $data = $this->responseData($response);
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

        $data = $this->responseData($response);

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

    /**
     * Polar sends `detail` as a message for 403/404 errors but as a list of
     * field errors for 422 — keep only a message callers can show as-is.
     *
     * @return array<string, mixed>
     */
    private function responseData(Response $response): array
    {
        $data = $response->json();

        if (! is_array($data)) {
            return [];
        }

        if (array_key_exists('detail', $data) && ! is_string($data['detail'])) {
            unset($data['detail']);
        }

        return $data;
    }
}
