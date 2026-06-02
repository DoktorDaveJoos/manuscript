<?php

namespace App\Jobs\Editorial;

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\EditorialNotesAgent;
use App\Ai\Support\TextPrep;
use App\Jobs\Concerns\PersistsChapterAnalysis;
use App\Jobs\Concerns\UpdatesEditorialReview;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\EditorialReview;
use App\Models\EditorialReviewChapterNote;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Per-chapter unit of an editorial review: refreshes a stale chapter analysis
 * (if needed) and runs the editorial gap-fill agent to produce that chapter's
 * notes. One short job per chapter so the review never exceeds a worker timeout.
 */
class AnalyzeReviewChapterJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, PersistsChapterAnalysis, Queueable, SerializesModels, UpdatesEditorialReview;

    public int $tries = 1;

    public int $timeout = 480;

    public function __construct(
        public Book $book,
        public EditorialReview $review,
        public int $chapterId,
        public int $position,
        public int $total,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $setting = AiSetting::activeProvider();

        if (! $setting || ! $setting->isConfigured()) {
            return;
        }

        $setting->injectConfig();

        $this->updateReviewProgress(
            $this->review,
            'analyzing',
            current_chapter: $this->position,
            total_chapters: $this->total,
        );

        $chapter = $this->book->chapters()
            ->with(['scenes', 'analyses'])
            ->find($this->chapterId);

        if (! $chapter) {
            return;
        }

        $content = $chapter->getFullContent();

        if (empty($content)) {
            return;
        }

        $capped = TextPrep::plainTextCapped($content);

        if ($chapter->needsAiPreparation()) {
            $this->refreshChapterAnalysis($chapter, $capped);
        }

        $this->gapFillChapter($chapter, $capped);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }

    /**
     * Phase 0 — refresh a stale chapter analysis. Failures are logged and
     * skipped so a single bad chapter never derails the whole review.
     */
    private function refreshChapterAnalysis(Chapter $chapter, string $capped): void
    {
        try {
            $agent = new ChapterAnalyzer($this->book);
            $response = $agent->prompt("Analyze this chapter:\n\nTitle: {$chapter->title}\n\n{$capped}", timeout: 180);

            $this->persistChapterAnalysis($this->book, $chapter, $response->toArray());

            $chapter->update([
                'prepared_content_hash' => $chapter->content_hash,
            ]);
        } catch (Throwable $e) {
            report($e);
            Log::warning("Editorial review: chapter refresh failed for chapter {$chapter->id}", [
                'review_id' => $this->review->id,
                'chapter_id' => $chapter->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Phase 1 — gap-fill editorial notes for one chapter.
     */
    private function gapFillChapter(Chapter $chapter, string $capped): void
    {
        $notes = $this->reusableNotes($chapter);

        if ($notes === null) {
            try {
                $agent = new EditorialNotesAgent(
                    book: $this->book,
                    existingAnalysis: $this->buildExistingAnalysisContext($chapter),
                    writingStyle: $this->book->writing_style_display,
                    characterData: $this->buildCharacterData(),
                );

                $notes = $agent->prompt($capped, timeout: 180)->toArray();
            } catch (Throwable $e) {
                report($e);
                Log::warning("Editorial review: chapter gap-fill failed for chapter {$chapter->id}", [
                    'review_id' => $this->review->id,
                    'chapter_id' => $chapter->id,
                    'error' => $e->getMessage(),
                ]);

                return;
            }
        }

        $this->review->chapterNotes()->create([
            'chapter_id' => $chapter->id,
            'content_hash' => $chapter->content_hash,
            'notes' => $notes,
        ]);
    }

    /**
     * Notes from a prior review of this book for an unchanged chapter, or null
     * when the chapter must be (re)analyzed. Lets a re-run skip the AI call when
     * the chapter content has not changed since it was last reviewed.
     *
     * @return array<string, mixed>|null
     */
    private function reusableNotes(Chapter $chapter): ?array
    {
        if (! $chapter->content_hash) {
            return null;
        }

        $existing = EditorialReviewChapterNote::query()
            ->where('chapter_id', $chapter->id)
            ->where('content_hash', $chapter->content_hash)
            ->where('editorial_review_id', '!=', $this->review->id)
            ->whereHas('editorialReview', fn ($query) => $query->where('book_id', $this->book->id))
            ->latest('id')
            ->first();

        return $existing?->notes;
    }

    /**
     * Build a context string from existing chapter analysis data.
     */
    private function buildExistingAnalysisContext(Chapter $chapter): string
    {
        $parts = [];

        if ($chapter->summary) {
            $parts[] = "Summary: {$chapter->summary}";
        }

        if ($chapter->tension_score !== null) {
            $parts[] = "Tension score: {$chapter->tension_score}/10";
        }

        if ($chapter->pacing_feel) {
            $parts[] = "Pacing: {$chapter->pacing_feel}";
        }

        if ($chapter->scene_purpose) {
            $parts[] = "Scene purpose: {$chapter->scene_purpose}";
        }

        if ($chapter->value_shift) {
            $parts[] = "Value shift: {$chapter->value_shift}";
        }

        if ($chapter->hook_type) {
            $parts[] = "Hook type: {$chapter->hook_type}";
        }

        $analyses = $chapter->analyses->map(function ($analysis) {
            $result = is_array($analysis->result) ? json_encode($analysis->result) : $analysis->result;

            return "[{$analysis->type->value}]: {$result}";
        })->implode("\n");

        if ($analyses) {
            $parts[] = "Manuscript-level analyses:\n{$analyses}";
        }

        return implode("\n", $parts);
    }

    /**
     * Build character/entity data string from wiki entries.
     */
    private function buildCharacterData(): string
    {
        $entries = $this->book->wikiEntries()
            ->where('kind', 'character')
            ->get(['name', 'description']);

        if ($entries->isEmpty()) {
            return '';
        }

        return $entries->map(fn ($entry) => "{$entry->name}: {$entry->description}")->implode("\n");
    }
}
