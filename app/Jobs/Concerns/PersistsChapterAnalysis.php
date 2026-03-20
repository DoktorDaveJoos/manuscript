<?php

namespace App\Jobs\Concerns;

use App\Models\Book;
use App\Models\Chapter;

trait PersistsChapterAnalysis
{
    /**
     * Persist chapter analysis response fields.
     */
    private function persistChapterAnalysis(Book $book, Chapter $chapter, array $response): void
    {
        $chapter->update([
            'summary' => $response['summary'] ?? null,
            'tension_score' => $response['tension_score'] ?? null,
            'hook_score' => $response['hook_score'] ?? null,
            'hook_type' => $response['hook_type'] ?? null,
            'scene_purpose' => $response['scene_purpose'] ?? null,
            'value_shift' => $response['value_shift'] ?? null,
            'emotional_state_open' => $response['emotional_state_open'] ?? null,
            'emotional_state_close' => $response['emotional_state_close'] ?? null,
            'emotional_shift_magnitude' => $response['emotional_shift_magnitude'] ?? null,
            'micro_tension_score' => $response['micro_tension_score'] ?? null,
            'pacing_feel' => $response['pacing_feel'] ?? null,
            'entry_hook_score' => $response['entry_hook_score'] ?? null,
            'exit_hook_score' => $response['hook_score'] ?? null,
            'sensory_grounding' => $response['sensory_grounding'] ?? null,
            'information_delivery' => $response['information_delivery'] ?? null,
            'analyzed_at' => now(),
        ]);
    }
}
