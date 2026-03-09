<?php

namespace App\Http\Controllers;

use App\Jobs\PrepareBookForAi;
use App\Models\AiSetting;
use App\Models\AppSetting;
use App\Models\Book;
use Illuminate\Http\JsonResponse;

class AiPreparationController extends Controller
{
    public function start(Book $book): JsonResponse
    {
        $setting = AiSetting::activeProvider();

        if (! AppSetting::showAiFeatures() || ! $setting?->isConfigured()) {
            return response()->json([
                'message' => 'AI is not enabled or no API key configured.',
            ], 422);
        }

        // Cancel any running preparation
        $book->aiPreparations()
            ->whereNotIn('status', ['completed', 'failed'])
            ->update(['status' => 'failed', 'error_message' => 'Superseded by new preparation']);

        $preparation = $book->aiPreparations()->create([
            'status' => 'pending',
        ]);

        PrepareBookForAi::dispatch($book, $preparation);

        return response()->json($preparation);
    }

    public function status(Book $book): JsonResponse
    {
        $preparation = $book->aiPreparations()
            ->latest()
            ->first();

        if (! $preparation) {
            return response()->json(null);
        }

        return response()->json($preparation);
    }
}
