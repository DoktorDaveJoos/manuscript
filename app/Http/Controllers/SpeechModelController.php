<?php

namespace App\Http\Controllers;

use App\Jobs\DownloadSpeechModelJob;
use App\Services\Speech\SpeechModelManager;
use Illuminate\Http\JsonResponse;

/**
 * Lifecycle of the on-device Whisper model: status polling for the settings
 * UI, starting the (queued) download, and removing the model to free disk
 * space. File presence is the source of truth — see SpeechModelManager.
 */
class SpeechModelController extends Controller
{
    public function show(SpeechModelManager $manager): JsonResponse
    {
        return response()->json($manager->status());
    }

    public function store(SpeechModelManager $manager): JsonResponse
    {
        if (! $manager->isReady()) {
            // Mark before dispatching so the response (and any poll racing the
            // queue worker) already reads as downloading, and a stale error
            // state from a previous attempt is cleared.
            $manager->markDownloading(0);

            DownloadSpeechModelJob::dispatch();
        }

        return response()->json($manager->status());
    }

    public function destroy(SpeechModelManager $manager): JsonResponse
    {
        $manager->delete();

        return response()->json($manager->status());
    }
}
