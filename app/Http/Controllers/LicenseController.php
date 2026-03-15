<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivateLicenseRequest;
use App\Models\Book;
use App\Models\License;
use App\Services\LemonSqueezyService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
    public function __construct(private LemonSqueezyService $lemonSqueezy) {}

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
            $result = $this->lemonSqueezy->activate($key, $hostname);
        } catch (ConnectionException) {
            return response()->json([
                'message' => __('Could not reach the license server. Please check your internet connection.'),
            ], 503);
        }

        if (! $result['success']) {
            return response()->json([
                'message' => $result['data']['error'] ?? __('Invalid license key.'),
            ], 422);
        }

        $meta = $result['data']['meta'] ?? [];

        if (! License::verifyMeta($meta)) {
            return response()->json([
                'message' => __('This license key is not valid for Manuscript.'),
            ], 422);
        }

        $instance = $result['data']['instance'] ?? [];
        $licenseKeyData = $result['data']['license_key'] ?? [];

        DB::transaction(function () use ($key, $hostname, $instance, $licenseKeyData, $meta) {
            License::query()->delete();

            License::create([
                'license_key' => $key,
                'activated' => true,
                'instance_id' => $instance['id'] ?? null,
                'instance_name' => $hostname,
                'license_key_id' => $licenseKeyData['id'] ?? null,
                'status' => $licenseKeyData['status'] ?? 'active',
                'customer_name' => $meta['customer_name'] ?? null,
                'customer_email' => $meta['customer_email'] ?? null,
                'product_name' => $meta['product_name'] ?? null,
                'activation_limit' => $licenseKeyData['activation_limit'] ?? null,
                'activation_usage' => $licenseKeyData['activation_usage'] ?? 0,
                'expires_at' => $licenseKeyData['expires_at'] ?? null,
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
            $result = $this->lemonSqueezy->deactivate(
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
                'message' => $result['data']['error'] ?? __('Failed to deactivate license.'),
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
            $result = $this->lemonSqueezy->validate(
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
