<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivateLicenseRequest;
use App\Models\Book;
use App\Models\License;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
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

        if (! License::validate($key)) {
            return response()->json([
                'message' => 'Invalid license key.',
            ], 422);
        }

        // Remove any existing license before activating new one
        License::query()->delete();

        License::create([
            'key' => $key,
            'activated' => true,
        ]);

        return response()->json([
            'message' => 'License activated successfully.',
        ]);
    }

    public function deactivate(): JsonResponse
    {
        License::query()->delete();

        return response()->json([
            'message' => 'License deactivated.',
        ]);
    }
}
