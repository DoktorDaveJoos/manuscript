<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisType;
use App\Enums\ChapterStatus;
use App\Models\Book;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function show(Book $book): Response
    {
        $book->load([
            'storylines' => fn ($q) => $q->orderBy('sort_order'),
            'storylines.chapters' => fn ($q) => $q
                ->select('id', 'book_id', 'storyline_id', 'title', 'reader_order', 'status', 'word_count', 'summary', 'tension_score', 'hook_score', 'hook_type')
                ->orderBy('reader_order'),
        ]);

        $chapters = $book->storylines->flatMap->chapters;
        $totalWords = $chapters->sum('word_count');
        $chapterCount = $chapters->count();

        $statusCounts = [
            'draft' => $chapters->where('status', ChapterStatus::Draft)->count(),
            'revised' => $chapters->where('status', ChapterStatus::Revised)->count(),
            'final' => $chapters->where('status', ChapterStatus::Final)->count(),
        ];

        $aiPreparation = $book->aiPreparations()->latest()->first();

        return Inertia::render('books/dashboard', [
            'book' => $book->only('id', 'title', 'author', 'language', 'ai_enabled', 'storylines'),
            'stats' => [
                'total_words' => $totalWords,
                'chapter_count' => $chapterCount,
                'estimated_pages' => $chapterCount > 0 ? (int) ceil($totalWords / 250) : 0,
                'reading_time_minutes' => $chapterCount > 0 ? (int) ceil($totalWords / 230) : 0,
            ],
            'status_counts' => $statusCounts,
            'health_metrics' => $this->buildHealthMetrics($book, $chapters),
            'suggested_next' => $this->buildSuggestedNext($book),
            'ai_preparation' => $aiPreparation,
            'story_bible' => $book->story_bible,
        ]);
    }

    /**
     * Build health metrics from per-chapter analysis data.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Chapter>  $chapters
     * @return array{composite_score: int, metrics: list<array{label: string, score: int}>, last_analyzed_at: string, attention_items: list<array{type: string, title: string, description: string, severity: string}>}|null
     */
    private function buildHealthMetrics(Book $book, $chapters): ?array
    {
        $analyzedChapters = $chapters->filter(fn ($ch) => $ch->hook_score !== null);

        if ($analyzedChapters->isEmpty()) {
            return $this->buildLegacyHealthMetrics($book);
        }

        $metrics = [];
        $attentionItems = [];

        // Hooks (35% weight): average hook_score × 10
        $avgHook = $analyzedChapters->avg('hook_score');
        $hookScore = min(100, max(0, (int) round($avgHook * 10)));
        $metrics[] = ['label' => 'Hooks', 'score' => $hookScore];

        // Pacing (25% weight): coefficient of variation of word_count
        $wordCounts = $chapters->pluck('word_count')->filter(fn ($w) => $w > 0);
        if ($wordCounts->count() > 1) {
            $mean = $wordCounts->avg();
            $variance = $wordCounts->map(fn ($w) => pow($w - $mean, 2))->avg();
            $cv = $mean > 0 ? sqrt($variance) / $mean : 0;
            // CV of 0.15-0.35 is ideal (some variation). Map to 0-100.
            $pacingScore = min(100, max(0, (int) round(100 - abs($cv - 0.25) * 200)));
        } else {
            $pacingScore = 50;
        }
        $metrics[] = ['label' => 'Pacing', 'score' => $pacingScore];

        // Tension (25% weight): tension arc progression
        $tensionChapters = $analyzedChapters->filter(fn ($ch) => $ch->tension_score !== null);
        if ($tensionChapters->count() > 2) {
            $avgTension = $tensionChapters->avg('tension_score');
            $tensionScore = min(100, max(0, (int) round($avgTension * 10)));
        } else {
            $tensionScore = 50;
        }
        $metrics[] = ['label' => 'Tension', 'score' => $tensionScore];

        // Weave (15% weight): storyline distribution balance
        $storylineCounts = $chapters->groupBy('storyline_id')->map->count();
        if ($storylineCounts->count() > 1) {
            $maxCount = $storylineCounts->max();
            $minCount = $storylineCounts->min();
            $weaveScore = $maxCount > 0
                ? min(100, max(0, (int) round(($minCount / $maxCount) * 100)))
                : 50;
        } else {
            $weaveScore = 75;
        }
        $metrics[] = ['label' => 'Weave', 'score' => $weaveScore];

        // Composite score (weighted)
        $compositeScore = (int) round(
            $hookScore * 0.35 + $pacingScore * 0.25 + $tensionScore * 0.25 + $weaveScore * 0.15
        );

        // Attention items: weakest 3 chapter hooks
        $weakestHooks = $analyzedChapters
            ->sortBy('hook_score')
            ->take(3);

        foreach ($weakestHooks as $chapter) {
            if ($chapter->hook_score <= 5) {
                $severity = $chapter->hook_score <= 3 ? 'high' : 'medium';
                $attentionItems[] = [
                    'type' => 'Hooks',
                    'title' => "Ch{$chapter->reader_order}: {$chapter->title}",
                    'description' => "Hook score {$chapter->hook_score}/10 ({$chapter->hook_type})",
                    'severity' => $severity,
                ];
            }
        }

        $lastAnalyzed = $analyzedChapters->max('updated_at');

        return [
            'composite_score' => $compositeScore,
            'metrics' => $metrics,
            'last_analyzed_at' => $lastAnalyzed?->toISOString() ?? now()->toISOString(),
            'attention_items' => array_slice($attentionItems, 0, 3),
        ];
    }

    /**
     * Fallback to legacy analysis-based health metrics.
     *
     * @return array{composite_score: int, metrics: list<array{label: string, score: int}>, last_analyzed_at: string, attention_items: list<array{type: string, title: string, description: string, severity: string}>}|null
     */
    private function buildLegacyHealthMetrics(Book $book): ?array
    {
        $healthTypes = [
            AnalysisType::Pacing->value => 'Pacing',
            AnalysisType::Plothole->value => 'Hooks',
            AnalysisType::Density->value => 'Tension',
            AnalysisType::CharacterConsistency->value => 'Weave',
        ];

        $enumTypes = [
            AnalysisType::Pacing,
            AnalysisType::Plothole,
            AnalysisType::Density,
            AnalysisType::CharacterConsistency,
        ];

        $analyses = $book->analyses()
            ->whereNull('chapter_id')
            ->whereIn('type', $enumTypes)
            ->latest()
            ->get()
            ->unique('type');

        if ($analyses->isEmpty()) {
            return null;
        }

        $metrics = [];
        $attentionItems = [];

        foreach ($healthTypes as $typeValue => $label) {
            $analysis = $analyses->first(fn ($a) => $a->type->value === $typeValue);
            if (! $analysis) {
                continue;
            }

            $score = min(100, max(0, (int) (($analysis->result['score'] ?? 0) * 10)));
            $metrics[] = ['label' => $label, 'score' => $score];

            $findings = $analysis->result['findings'] ?? [];
            foreach ($findings as $finding) {
                $attentionItems[] = [
                    'type' => $label,
                    'title' => $finding['title'] ?? $label.' issue',
                    'description' => $finding['description'] ?? '',
                    'severity' => $finding['severity'] ?? 'medium',
                ];
            }
        }

        $compositeScore = count($metrics) > 0
            ? (int) round(collect($metrics)->avg('score'))
            : 0;

        $attentionItems = array_slice($attentionItems, 0, 3);

        $lastAnalyzedAt = $analyses->max('created_at');

        return [
            'composite_score' => $compositeScore,
            'metrics' => $metrics,
            'last_analyzed_at' => $lastAnalyzedAt->toISOString(),
            'attention_items' => $attentionItems,
        ];
    }

    /**
     * @return array{title: string, description: string}|null
     */
    private function buildSuggestedNext(Book $book): ?array
    {
        $suggestion = $book->analyses()
            ->where('type', AnalysisType::NextChapterSuggestion)
            ->latest()
            ->first();

        if (! $suggestion || ! $suggestion->result) {
            return null;
        }

        return [
            'title' => $suggestion->result['title'] ?? 'Next Chapter',
            'description' => $suggestion->result['description'] ?? '',
        ];
    }
}
