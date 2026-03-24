<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisType;
use App\Enums\ChapterStatus;
use App\Models\AiUsageLog;
use App\Models\Book;
use App\Models\License;
use App\Models\WritingSession;
use App\Services\HealthScoreCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function show(Book $book): Response
    {
        $book->load([
            'storylines' => fn ($q) => $q->orderBy('sort_order'),
            'storylines.chapters' => fn ($q) => $q
                ->select('id', 'book_id', 'storyline_id', 'title', 'reader_order', 'status', 'word_count', 'summary', 'tension_score', 'hook_score', 'hook_type', 'scene_purpose', 'value_shift', 'emotional_shift_magnitude', 'micro_tension_score', 'pacing_feel', 'entry_hook_score', 'exit_hook_score', 'sensory_grounding', 'information_delivery')
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

        $isLicensed = License::isActive();

        $aiPreparation = $isLicensed ? $book->aiPreparations()->latest()->first() : null;

        $todaySession = $isLicensed
            ? $book->writingSessions()->whereDate('date', now()->toDateString())->first()
            : null;

        $streak = $isLicensed ? $this->calculateStreak($book, $todaySession) : 0;

        $healthMetrics = $isLicensed ? $this->buildHealthMetrics($book, $chapters) : null;

        // Auto-detect milestone
        if ($isLicensed && $book->target_word_count && $totalWords >= $book->target_word_count && ! $book->milestone_reached_at) {
            $book->update(['milestone_reached_at' => now()]);
        }

        return Inertia::render('books/dashboard', [
            'book' => $book->only('id', 'title', 'author', 'language', 'storylines'),
            'stats' => [
                'total_words' => $totalWords,
                'chapter_count' => $chapterCount,
                'estimated_pages' => $chapterCount > 0 ? (int) ceil($totalWords / 250) : 0,
                'reading_time_minutes' => $chapterCount > 0 ? (int) ceil($totalWords / 230) : 0,
            ],
            'status_counts' => $statusCounts,
            'health_metrics' => $healthMetrics,
            'suggested_next' => $isLicensed ? $this->buildSuggestedNext($book) : null,
            'ai_preparation' => $aiPreparation,
            'story_bible' => $book->story_bible,
            'writing_goal' => $isLicensed ? [
                'daily_word_count_goal' => $book->daily_word_count_goal,
                'today_words' => $todaySession?->words_written ?? 0,
                'goal_met_today' => (bool) $todaySession?->goal_met,
                'streak' => $streak,
            ] : null,
            'writing_heatmap' => $isLicensed ? $this->buildWritingHeatmap($book) : [],
            'health_history' => $isLicensed ? $this->buildHealthHistory($book) : [],
            'manuscript_target' => $isLicensed ? $this->buildManuscriptTarget($book, $totalWords) : null,
            'ai_usage' => $isLicensed ? [
                'input_tokens' => $book->ai_input_tokens,
                'output_tokens' => $book->ai_output_tokens,
                'cost_display' => $book->ai_cost_display,
                'reset_at' => $book->ai_usage_reset_at?->toISOString(),
                'request_count' => $book->ai_request_count,
                'avg_cost_display' => $book->ai_avg_cost_display,
                'features_breakdown' => AiUsageLog::featureBreakdown($book->id, $book->ai_usage_reset_at),
                'monthly_usage' => AiUsageLog::monthlyUsage($book->id, $book->ai_usage_reset_at),
            ] : null,
            'nanowrimo' => $isLicensed ? $this->buildNanowrimoData($book, $totalWords) : null,
            'streak' => $streak,
        ]);
    }

    public function dismissMilestone(Book $book): JsonResponse
    {
        $book->update(['milestone_dismissed' => true]);

        return response()->json(['dismissed' => true]);
    }

    /**
     * Build health metrics from per-chapter analysis data.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Chapter>  $chapters
     * @return array{composite_score: int, metrics: list<array{label: string, score: int}>, last_analyzed_at: string, attention_items: list<array>}|null
     */
    private function buildHealthMetrics(Book $book, $chapters): ?array
    {
        $analyzed = $chapters->filter(fn ($ch) => $ch->hook_score !== null);

        if ($analyzed->isEmpty()) {
            return $this->buildLegacyHealthMetrics($book);
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

        $attentionItems = [];

        foreach ($analyzed as $chapter) {
            if ($chapter->scene_purpose === 'transition' && $chapter->value_shift === null) {
                $attentionItems[] = [
                    'chapter_order' => $chapter->reader_order,
                    'chapter_title' => $chapter->title,
                    'description_key' => 'transitionNoValueShift',
                    'severity' => 'medium',
                ];
            }

            if (in_array($chapter->information_delivery, ['info_dump', 'exposition_heavy'])) {
                $attentionItems[] = [
                    'chapter_order' => $chapter->reader_order,
                    'chapter_title' => $chapter->title,
                    'description_key' => 'informationDelivery',
                    'description_params' => ['type' => $chapter->information_delivery],
                    'severity' => $chapter->information_delivery === 'info_dump' ? 'high' : 'medium',
                ];
            }

            if ($chapter->tension_score !== null && $chapter->tension_score <= 3
                && $chapter->micro_tension_score !== null && $chapter->micro_tension_score <= 3) {
                $attentionItems[] = [
                    'chapter_order' => $chapter->reader_order,
                    'chapter_title' => $chapter->title,
                    'description_key' => 'lowTension',
                    'severity' => 'high',
                ];
            }

            if ($chapter->sensory_grounding !== null && $chapter->sensory_grounding <= 1) {
                $attentionItems[] = [
                    'chapter_order' => $chapter->reader_order,
                    'chapter_title' => $chapter->title,
                    'description_key' => 'lowSensory',
                    'description_params' => ['count' => $chapter->sensory_grounding],
                    'severity' => 'medium',
                ];
            }
        }

        $weakestHooks = $analyzed->sortBy('hook_score')->take(3);
        foreach ($weakestHooks as $chapter) {
            if ($chapter->hook_score <= 7) {
                $attentionItems[] = [
                    'chapter_order' => $chapter->reader_order,
                    'chapter_title' => $chapter->title,
                    'description_key' => 'weakHook',
                    'description_params' => ['score' => $chapter->hook_score, 'type' => $chapter->hook_type],
                    'severity' => $chapter->hook_score <= 3 ? 'high' : ($chapter->hook_score <= 5 ? 'medium' : 'low'),
                ];
            }
        }

        $lastAnalyzed = $analyzed->max('updated_at');

        return [
            'composite_score' => $scores['composite'],
            'metrics' => $metrics,
            'last_analyzed_at' => $lastAnalyzed?->toISOString() ?? now()->toISOString(),
            'attention_items' => array_slice($attentionItems, 0, 5),
        ];
    }

    /**
     * Fallback to legacy analysis-based health metrics.
     *
     * @return array{composite_score: int, metrics: list<array{label: string, score: int}>, last_analyzed_at: string, attention_items: list<array>}|null
     */
    private function buildLegacyHealthMetrics(Book $book): ?array
    {
        $healthTypes = [
            AnalysisType::Pacing->value => 'pacing',
            AnalysisType::Plothole->value => 'hooks',
            AnalysisType::Density->value => 'tension',
            AnalysisType::CharacterConsistency->value => 'weave',
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

    private function calculateStreak(Book $book, ?WritingSession $todaySession): int
    {
        $streak = 0;

        if ($todaySession?->goal_met) {
            $streak = 1;
        }

        $sessions = $book->writingSessions()
            ->where('goal_met', true)
            ->whereDate('date', '<', now()->toDateString())
            ->orderByDesc('date')
            ->limit(365)
            ->pluck('date');

        $checkDate = now()->subDay();

        foreach ($sessions as $sessionDate) {
            $dateString = $sessionDate instanceof \Carbon\Carbon
                ? $sessionDate->toDateString()
                : substr((string) $sessionDate, 0, 10);

            if ($dateString === $checkDate->toDateString()) {
                $streak++;
                $checkDate = $checkDate->subDay();
            } else {
                break;
            }
        }

        return $streak;
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
            'chapter_id' => $suggestion->result['chapter_id'] ?? $suggestion->chapter_id,
        ];
    }

    /**
     * @return list<array{date: string, words: int, goal_met: bool}>
     */
    private function buildWritingHeatmap(Book $book): array
    {
        return $book->writingSessions()
            ->where('date', '>=', now()->subDays(364))
            ->get(['date', 'words_written', 'goal_met'])
            ->map(fn ($session) => [
                'date' => $session->date instanceof \Carbon\Carbon
                    ? $session->date->toDateString()
                    : substr((string) $session->date, 0, 10),
                'words' => (int) $session->words_written,
                'goal_met' => (bool) $session->goal_met,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{date: string, composite: int, hooks: int, pacing: int, tension: int, weave: int|null, scene_purpose: int|null, tension_dynamics: int|null, emotional_arc: int|null, craft: int|null}>
     */
    private function buildHealthHistory(Book $book): array
    {
        return $book->healthSnapshots()
            ->where('recorded_at', '>=', now()->subDays(90))
            ->orderBy('recorded_at')
            ->get()
            ->map(fn ($snapshot) => [
                'date' => $snapshot->recorded_at->toDateString(),
                'composite' => $snapshot->composite_score,
                'hooks' => $snapshot->hooks_score,
                'pacing' => $snapshot->pacing_score,
                'tension' => $snapshot->tension_score,
                'weave' => $snapshot->weave_score,
                'scene_purpose' => $snapshot->scene_purpose_score,
                'tension_dynamics' => $snapshot->tension_dynamics_score,
                'emotional_arc' => $snapshot->emotional_arc_score,
                'craft' => $snapshot->craft_score,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{target_word_count: int|null, total_words: int, progress_percent: int, milestone_reached: bool, milestone_reached_at: string|null, milestone_dismissed: bool, days_writing: int}
     */
    private function buildManuscriptTarget(Book $book, int $totalWords): array
    {
        $daysWriting = $book->writingSessions()->distinct('date')->count('date');

        return [
            'target_word_count' => $book->target_word_count,
            'total_words' => $totalWords,
            'progress_percent' => $book->target_word_count
                ? min(100, (int) round(($totalWords / $book->target_word_count) * 100))
                : 0,
            'milestone_reached' => $book->milestone_reached_at !== null,
            'milestone_reached_at' => $book->milestone_reached_at?->toISOString(),
            'milestone_dismissed' => (bool) $book->milestone_dismissed,
            'days_writing' => $daysWriting,
        ];
    }

    /**
     * @return array{year: int, is_active: bool, target: int, total_words: int, progress_percent: int|float, days_remaining: int, days_elapsed: int, daily_pace: int, on_track: bool}|null
     */
    private function buildNanowrimoData(Book $book, int $totalWords): ?array
    {
        if (! $book->nanowrimo_year) {
            return null;
        }

        $nanoStart = Carbon::create($book->nanowrimo_year, 11, 1);
        $nanoEnd = Carbon::create($book->nanowrimo_year, 11, 30)->endOfDay();
        $target = 50000;
        $isActive = now()->between($nanoStart, $nanoEnd);
        $daysElapsed = $isActive ? (int) $nanoStart->diffInDays(now()) + 1 : 0;
        $daysRemaining = $isActive ? (int) now()->diffInDays($nanoEnd) : 0;
        $progressPercent = $target > 0 ? min(100, round(($totalWords / $target) * 100, 1)) : 0;
        $dailyPace = $daysElapsed > 0 ? (int) round($totalWords / $daysElapsed) : 0;
        $onTrack = $daysElapsed > 0 && ($totalWords / $daysElapsed) >= ($target / 30);

        return [
            'year' => $book->nanowrimo_year,
            'is_active' => $isActive,
            'target' => $target,
            'total_words' => $totalWords,
            'progress_percent' => $progressPercent,
            'days_remaining' => $daysRemaining,
            'days_elapsed' => $daysElapsed,
            'daily_pace' => $dailyPace,
            'on_track' => $onTrack,
        ];
    }
}
