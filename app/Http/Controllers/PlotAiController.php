<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisType;
use App\Jobs\RunAnalysisJob;
use App\Models\AiSetting;
use App\Models\Book;
use Illuminate\Http\JsonResponse;

class PlotAiController extends Controller
{
    public function runPlotHealth(Book $book): JsonResponse
    {
        $this->ensureAiConfigured();

        return $this->runAndReturn($book, AnalysisType::GenreHealth);
    }

    public function detectPlotHoles(Book $book): JsonResponse
    {
        $this->ensureAiConfigured();

        return $this->runAndReturn($book, AnalysisType::Plothole);
    }

    public function suggestBeats(Book $book): JsonResponse
    {
        $this->ensureAiConfigured();

        return $this->runAndReturn($book, AnalysisType::NextChapterSuggestion);
    }

    public function generateTensionArc(Book $book): JsonResponse
    {
        $this->ensureAiConfigured();

        $chapters = $book->chapters()->whereNotNull('tension_score')->get();

        if ($chapters->isEmpty()) {
            return response()->json([
                'message' => __('No tension data available. Run chapter analysis first via AI Preparation.'),
            ], 422);
        }

        $tensionData = $chapters->map(fn ($ch) => [
            'chapter_id' => $ch->id,
            'title' => $ch->title,
            'reader_order' => $ch->reader_order,
            'tension_score' => $ch->tension_score,
        ])->sortBy('reader_order')->values();

        return response()->json([
            'tension_arc' => $tensionData,
            'generated_at' => now()->toISOString(),
        ]);
    }

    public function analysisStatus(Book $book): JsonResponse
    {
        $analyses = $book->analyses()
            ->whereNull('chapter_id')
            ->get()
            ->keyBy(fn ($a) => $a->type->value);

        return response()->json(['analyses' => $analyses]);
    }

    private function runAndReturn(Book $book, AnalysisType $type): JsonResponse
    {
        RunAnalysisJob::dispatchSync($book, $type);

        $analysis = $book->analyses()
            ->where('type', $type)
            ->whereNull('chapter_id')
            ->latest()
            ->first();

        return response()->json([
            'analysis' => $analysis?->result,
        ]);
    }

    private function ensureAiConfigured(): void
    {
        set_time_limit(300);

        $setting = AiSetting::activeProvider();

        abort_if(
            ! $setting || ! $setting->isConfigured(),
            422,
            __('No AI provider configured.'),
        );

        $setting->injectConfig();
    }
}
