<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisType;
use App\Enums\ChapterStatus;
use App\Models\Book;
use App\Models\License;
use App\Models\WritingSession;
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
                ->select('id', 'book_id', 'storyline_id', 'title', 'reader_order', 'status', 'word_count', 'summary', 'updated_at')
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

        $todaySession = $isLicensed
            ? $book->writingSessions()->whereDate('date', now()->toDateString())->first()
            : null;

        $streak = $isLicensed ? $this->calculateStreak($book, $todaySession) : 0;

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
            'suggested_next' => $this->buildSuggestedNext($book, $isLicensed),
            'writing_goal' => $isLicensed ? [
                'daily_word_count_goal' => $book->daily_word_count_goal,
                'today_words' => $todaySession?->words_written ?? 0,
                'goal_met_today' => (bool) $todaySession?->goal_met,
                'streak' => $streak,
            ] : null,
            'writing_heatmap' => $isLicensed ? $this->buildWritingHeatmap($book) : [],
            'manuscript_target' => $isLicensed ? $this->buildManuscriptTarget($book, $totalWords) : null,
        ]);
    }

    public function dismissMilestone(Book $book): JsonResponse
    {
        $book->update(['milestone_dismissed' => true]);

        return response()->json(['dismissed' => true]);
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
            $dateString = $sessionDate instanceof Carbon
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
     * @return array{title: string, description: string, chapter_id: int|null}|null
     */
    private function buildSuggestedNext(Book $book, bool $isLicensed): ?array
    {
        if ($isLicensed) {
            $suggestion = $book->analyses()
                ->where('type', AnalysisType::NextChapterSuggestion)
                ->latest()
                ->first();

            if ($suggestion && $suggestion->result) {
                return [
                    'title' => $suggestion->result['title'] ?? 'Next Chapter',
                    'description' => $suggestion->result['description'] ?? '',
                    'chapter_id' => $suggestion->result['chapter_id'] ?? $suggestion->chapter_id,
                ];
            }
        }

        // Fallback: most recently edited chapter (uses already-loaded relation)
        $recentChapter = $book->storylines
            ->flatMap->chapters
            ->sortByDesc('updated_at')
            ->first();

        if (! $recentChapter) {
            return null;
        }

        return [
            'title' => $recentChapter->title,
            'description' => '',
            'chapter_id' => $recentChapter->id,
            'last_edited_at' => $recentChapter->updated_at->toISOString(),
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
                'date' => $session->date instanceof Carbon
                    ? $session->date->toDateString()
                    : substr((string) $session->date, 0, 10),
                'words' => (int) $session->words_written,
                'goal_met' => (bool) $session->goal_met,
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
}
