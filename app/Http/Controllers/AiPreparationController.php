<?php

namespace App\Http\Controllers;

use App\Jobs\PrepareBookForAi;
use App\Models\AiSetting;
use App\Models\AppSetting;
use App\Models\Book;
use App\Services\AiPreparationRetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;

class AiPreparationController extends Controller
{
    public function start(Book $book): JsonResponse
    {
        $setting = AiSetting::activeProvider();

        if (! AppSetting::showAiFeatures() || ! $setting?->isConfigured()) {
            return response()->json([
                'message' => __('AI is not enabled or no API key configured.'),
            ], 422);
        }

        // Cancel any running preparation and its batch
        $existing = $book->aiPreparations()
            ->whereNotIn('status', ['completed', 'failed'])
            ->get();

        foreach ($existing as $prep) {
            if ($prep->batch_id) {
                Bus::findBatch($prep->batch_id)?->cancel();
            }
            $prep->update(['status' => 'failed', 'error_message' => __('Superseded by new preparation')]);
        }

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

    public function retry(Book $book, AiPreparationRetryService $service): JsonResponse
    {
        $setting = AiSetting::activeProvider();

        if (! AppSetting::showAiFeatures() || ! $setting?->isConfigured()) {
            return response()->json([
                'message' => __('AI is not enabled or no API key configured.'),
            ], 422);
        }

        $preparation = $book->aiPreparations()->latest()->first();

        if (! $preparation) {
            return response()->json([
                'message' => __('No preparation found to retry.'),
            ], 404);
        }

        if (empty($preparation->phase_errors)) {
            return response()->json([
                'message' => __('Nothing to retry.'),
            ], 422);
        }

        $service->retry($book, $preparation);

        return response()->json($preparation->fresh());
    }
}
