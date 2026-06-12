<?php

namespace App\Http\Controllers;

use App\Services\Speech\SpeechModelManager;
use App\Services\Speech\WhisperTranscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpeechTranscriptionController extends Controller
{
    public function store(
        Request $request,
        WhisperTranscriber $transcriber,
        SpeechModelManager $manager,
    ): JsonResponse {
        $request->validate([
            // ~2 min of 16 kHz mono PCM is under 4 MB; 50 MB leaves headroom
            // without letting arbitrary huge uploads hit the transcriber.
            'audio' => ['required', 'file', 'max:51200'],
        ]);

        if (! $transcriber->isAvailable()) {
            return response()->json([
                'message' => 'Speech recognition is not available in this build.',
            ], 503);
        }

        if (! $manager->isReady()) {
            return response()->json([
                'message' => 'The speech model has not been downloaded yet.',
            ], 409);
        }

        return response()->json([
            'text' => $transcriber->transcribe($request->file('audio')->getRealPath()),
        ]);
    }
}
