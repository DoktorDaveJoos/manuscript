<?php

namespace App\Jobs\Editorial;

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\EditorialNotesAgent;
use App\Ai\Support\TextPrep;
use App\Enums\EditorialReviewErrorCode;
use App\Jobs\Concerns\PersistsChapterAnalysis;
use App\Jobs\Concerns\RunsManuscriptAnalyses;
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
use Illuminate\Queue\Attributes\FailOnTimeout;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Per-chapter unit of an editorial review: refreshes a stale chapter analysis
 * (if needed) and runs the editorial gap-fill agent to produce that chapter's
 * notes. One short job per chapter so the review never exceeds a worker timeout.
 */
#[FailOnTimeout]
class AnalyzeReviewChapterJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, PersistsChapterAnalysis, Queueable, RunsManuscriptAnalyses, SerializesModels, UpdatesEditorialReview;

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
            $this->markReviewFailed(
                $this->review,
                __('Chapter :chapter could not be analyzed because no configured AI provider is selected.', [
                    'chapter' => $this->position,
                ]),
                EditorialReviewErrorCode::NoProvider,
            );
            $this->batch()?->cancel();

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

        if ($chapter->needsAiPreparation() && ! $this->refreshChapterAnalysis($chapter, $capped)) {
            return;
        }

        $this->gapFillChapter($chapter, $capped);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
        $this->markReviewFailedFromThrowable(
            $this->review,
            $exception,
            __('The review worker stopped while analyzing chapter :chapter. Your completed work was saved.', [
                'chapter' => $this->position,
            ]),
        );
        $this->batch()?->cancel();
    }

    /**
     * Phase 0 — refresh a stale chapter analysis, including the manuscript
     * analyses (character consistency, plot deviation) that the synthesis
     * sections aggregate. Failures are logged and skipped so a single bad
     * chapter never derails the whole review — except temporary provider
     * failures, which halt the run so it can be resumed. Returns false when
     * the run was halted.
     */
    private function refreshChapterAnalysis(Chapter $chapter, string $capped): bool
    {
        try {
            $agent = new ChapterAnalyzer($this->book);
            $response = $agent->prompt("Analyze this chapter:\n\nTitle: {$chapter->title}\n\n{$capped}", timeout: 180);

            $this->persistChapterAnalysis($this->book, $chapter, $response->toArray());

            $this->runManuscriptAnalyses($this->book, $chapter);

            $chapter->update([
                'prepared_content_hash' => $chapter->content_hash,
            ]);
        } catch (Throwable $e) {
            report($e);

            if ($this->haltForProviderIssue($e, $chapter)) {
                return false;
            }

            Log::warning("Editorial review: chapter refresh failed for chapter {$chapter->id}", [
                'review_id' => $this->review->id,
                'chapter_id' => $chapter->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Phase 1 — gap-fill editorial notes for one chapter. Skipped when this
     * review already holds a note for the chapter's current content, so a
     * resumed run only pays for the chapters that are still missing.
     */
    private function gapFillChapter(Chapter $chapter, string $capped): void
    {
        if ($this->hasFreshNote($chapter)) {
            return;
        }

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

                $this->haltForFailure($e, $chapter);

                return;
            }
        }

        $this->review->chapterNotes()->updateOrCreate(
            ['chapter_id' => $chapter->id],
            [
                'content_hash' => $chapter->content_hash,
                'notes' => $notes,
            ],
        );
    }

    /**
     * Whether this review already has a note for the chapter's current
     * content. Notes whose hash no longer matches (the chapter was edited
     * between a failure and the resume) are pruned so they regenerate.
     */
    private function hasFreshNote(Chapter $chapter): bool
    {
        $existing = $this->review->chapterNotes()
            ->where('chapter_id', $chapter->id)
            ->latest('id')
            ->first();

        if (! $existing) {
            return false;
        }

        if ($chapter->content_hash !== null && $existing->content_hash === $chapter->content_hash) {
            return true;
        }

        $this->review->chapterNotes()->where('chapter_id', $chapter->id)->delete();

        return false;
    }

    /**
     * On a temporary provider failure (rate limit, overload, out of credits)
     * or an invalid API key, the whole run halts: the review is marked failed
     * with a resumable error code and the batch is cancelled so remaining
     * jobs exit without burning further AI calls. Returns true when the run
     * was halted.
     */
    private function haltForProviderIssue(Throwable $e, Chapter $chapter): bool
    {
        $code = EditorialReviewErrorCode::fromThrowable($e);

        if (! $code->shouldHaltRun()) {
            return false;
        }

        Log::warning("Editorial review: provider unavailable during chapter {$chapter->id}, halting for resume", [
            'review_id' => $this->review->id,
            'chapter_id' => $chapter->id,
            'error_code' => $code->value,
            'error' => $e->getMessage(),
        ]);

        $this->markReviewFailed(
            $this->review,
            __('The AI provider stopped while preparing chapter :chapter. Your completed work was saved.', [
                'chapter' => $this->position,
            ]),
            $code,
        );
        $this->batch()?->cancel();

        return true;
    }

    /**
     * Editorial notes are required for synthesis. Any failure here must halt
     * the batch; otherwise FinalizeEditorialReviewJob could publish a partial
     * report while silently omitting this chapter.
     */
    private function haltForFailure(Throwable $e, Chapter $chapter): void
    {
        $code = EditorialReviewErrorCode::fromThrowable($e);

        Log::warning("Editorial review: required notes failed for chapter {$chapter->id}, halting for resume", [
            'review_id' => $this->review->id,
            'chapter_id' => $chapter->id,
            'error_code' => $code->value,
            'error' => $e->getMessage(),
        ]);

        $this->markReviewFailed(
            $this->review,
            __('Editorial notes could not be generated for chapter :chapter. Your completed work was saved.', [
                'chapter' => $this->position,
            ]),
            $code,
        );
        $this->batch()?->cancel();
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
