<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Chapter;
use App\Services\HealthScoreCalculator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AiDashboardController extends Controller
{
    public function index(Book $book): Response
    {
        $book->load([
            'storylines' => fn ($q) => $q->orderBy('sort_order'),
            'storylines.chapters' => fn ($q) => $q
                ->select('id', 'book_id', 'storyline_id', 'title', 'reader_order', 'status', 'word_count', 'summary', 'tension_score', 'hook_score', 'hook_type', 'scene_purpose', 'value_shift', 'emotional_shift_magnitude', 'micro_tension_score', 'pacing_feel', 'entry_hook_score', 'exit_hook_score', 'sensory_grounding', 'information_delivery')
                ->orderBy('reader_order'),
        ]);

        $aiPreparation = $book->aiPreparations()->latest()->first();
        $isPrepared = $aiPreparation?->status === 'completed';

        $chapters = $book->storylines->flatMap->chapters;

        $healthMetrics = $isPrepared ? $this->buildHealthMetrics($chapters) : null;

        $analyzedChapters = $isPrepared
            ? $this->buildAnalyzedChapters($book)
            : null;

        $aiUsage = [
            'input_tokens' => $book->ai_input_tokens,
            'output_tokens' => $book->ai_output_tokens,
            'cost_display' => $book->ai_cost_display,
            'reset_at' => $book->ai_usage_reset_at?->toISOString(),
            'request_count' => $book->ai_request_count,
        ];

        return Inertia::render('books/ai-dashboard', [
            'book' => $book->only('id', 'title', 'author', 'language', 'storylines'),
            'is_prepared' => $isPrepared,
            'ai_preparation' => $aiPreparation,
            'health_metrics' => $healthMetrics,
            'analyzed_chapters' => $analyzedChapters,
            'ai_usage' => $aiUsage,
        ]);
    }

    /**
     * @param  Collection<int, Chapter>  $chapters
     * @return array{composite_score: int, metrics: list<array{label: string, score: int}>}|null
     */
    private function buildHealthMetrics($chapters): ?array
    {
        $analyzed = $chapters->filter(fn ($ch) => $ch->hook_score !== null);

        if ($analyzed->isEmpty()) {
            return null;
        }

        $scores = (new HealthScoreCalculator($analyzed))->calculate();

        $metrics = [
            ['label' => 'scene_purpose', 'score' => $scores['scene_purpose']],
            ['label' => 'pacing', 'score' => $scores['pacing']],
            ['label' => 'tension_dynamics', 'score' => $scores['tension_dynamics']],
            ['label' => 'hooks', 'score' => $scores['hooks']],
            ['label' => 'emotional_arc', 'score' => $scores['emotional_arc']],
            ['label' => 'craft', 'score' => $scores['craft']],
        ];

        return [
            'composite_score' => $scores['composite'],
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array{data: list<array>, current_page: int, last_page: int, total: int}
     */
    private function buildAnalyzedChapters(Book $book): array
    {
        $paginator = $book->chapters()
            ->with('storyline:id,name')
            ->orderBy('reader_order')
            ->paginate(5, ['id', 'book_id', 'storyline_id', 'title', 'reader_order', 'word_count', 'hook_score', 'hook_type', 'scene_purpose', 'value_shift', 'emotional_shift_magnitude', 'micro_tension_score', 'pacing_feel', 'entry_hook_score', 'exit_hook_score', 'sensory_grounding', 'information_delivery']);

        $data = $paginator->getCollection()->map(function ($chapter) {
            $findingsCount = 0;

            if ($chapter->scene_purpose === 'transition' && $chapter->value_shift === null) {
                $findingsCount++;
            }
            if (in_array($chapter->information_delivery, ['info_dump', 'exposition_heavy'])) {
                $findingsCount++;
            }
            if ($chapter->tension_score !== null && $chapter->tension_score <= 3
                && $chapter->micro_tension_score !== null && $chapter->micro_tension_score <= 3) {
                $findingsCount++;
            }
            if ($chapter->sensory_grounding !== null && $chapter->sensory_grounding <= 1) {
                $findingsCount++;
            }
            if ($chapter->hook_score !== null && $chapter->hook_score <= 5) {
                $findingsCount++;
            }

            return [
                'id' => $chapter->id,
                'title' => $chapter->title,
                'reader_order' => $chapter->reader_order,
                'score' => $chapter->hook_score,
                'word_count' => $chapter->word_count,
                'estimated_pages' => $chapter->word_count > 0 ? (int) ceil($chapter->word_count / 250) : 0,
                'findings_count' => $findingsCount,
                'storyline_name' => $chapter->storyline?->name,
                'is_analyzed' => $chapter->hook_score !== null,
            ];
        })->all();

        return [
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }
}
