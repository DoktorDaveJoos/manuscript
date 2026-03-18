<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivateLicenseRequest;
use App\Models\Book;
use App\Models\License;
use App\Services\PolarService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
    public function __construct(private PolarService $polar) {}

    public function index(): Response
    {
        $book = Book::query()->select('id', 'title')->first();

        return Inertia::render('settings/license', [
            'book' => $book?->only('id', 'title'),
        ]);
    }

    public function activate(ActivateLicenseRequest $request): JsonResponse
    {
        $key = $request->validated('license_key');
        $hostname = gethostname();

        try {
            $result = $this->polar->activate($key, $hostname);
        } catch (ConnectionException) {
            return response()->json([
                'message' => __('Could not reach the license server. Please check your internet connection.'),
            ], 503);
        }

        if (! $result['success']) {
            $detail = $result['data']['detail'] ?? null;

            return response()->json([
                'message' => $detail ?? __('Invalid license key.'),
            ], 422);
        }

        $data = $result['data'];
        $licenseKey = $data['license_key'] ?? [];
        $customer = $licenseKey['customer'] ?? [];

        DB::transaction(function () use ($key, $hostname, $data, $licenseKey, $customer) {
            License::query()->delete();

            License::create([
                'license_key' => $key,
                'activated' => true,
                'instance_id' => $data['id'] ?? null,
                'instance_name' => $hostname,
                'license_key_id' => $licenseKey['id'] ?? null,
                'status' => $licenseKey['status'] ?? 'granted',
                'customer_name' => $customer['name'] ?? null,
                'customer_email' => $customer['email'] ?? null,
                'product_name' => null,
                'activation_limit' => $licenseKey['limit_activations'] ?? null,
                'activation_usage' => $licenseKey['usage'] ?? 0,
                'expires_at' => $licenseKey['expires_at'] ?? null,
                'last_validated_at' => now(),
            ]);
        });

        return response()->json([
            'message' => __('License activated successfully.'),
        ]);
    }

    public function deactivate(): JsonResponse
    {
        $license = License::active();

        if (! $license) {
            return response()->json([
                'message' => __('No active license found.'),
            ], 404);
        }

        try {
            $result = $this->polar->deactivate(
                $license->license_key,
                $license->instance_id,
            );
        } catch (ConnectionException) {
            return response()->json([
                'message' => __('Could not reach the license server. Please check your internet connection and try again.'),
            ], 503);
        }

        if (! $result['success']) {
            return response()->json([
                'message' => $result['data']['detail'] ?? __('Failed to deactivate license.'),
            ], 422);
        }

        License::query()->delete();

        return response()->json([
            'message' => __('License deactivated.'),
        ]);
    }

    public function revalidate(): JsonResponse
    {
        $license = License::active();

        if (! $license || ! $license->instance_id) {
            return response()->json(['revalidated' => false]);
        }

        if (! $license->needsRevalidation()) {
            return response()->json(['revalidated' => false]);
        }

        try {
            $result = $this->polar->validate(
                $license->license_key,
                $license->instance_id,
            );
        } catch (ConnectionException) {
            // Silently skip — never revoke on failure
            return response()->json(['revalidated' => false]);
        }

        if ($result['success']) {
            $license->update(['last_validated_at' => now()]);

            return response()->json(['revalidated' => true]);
        }

        return response()->json(['revalidated' => false]);
    }
}
