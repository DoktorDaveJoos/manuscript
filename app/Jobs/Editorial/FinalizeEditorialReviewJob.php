<?php

namespace App\Jobs\Editorial;

use App\Ai\Agents\EditorialSummaryAgent;
use App\Ai\Agents\EditorialSynthesisAgent;
use App\Enums\AnalysisType;
use App\Enums\EditorialReviewErrorCode;
use App\Enums\EditorialSectionType;
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
use Illuminate\Support\Collection;
use Throwable;

/**
 * Terminal stage of an editorial review: once every chapter's notes exist, this
 * synthesizes the eight editorial sections and the executive summary. The work
 * is bounded (eight sections + one summary), so a single job is sufficient.
 */
#[FailOnTimeout]
class FinalizeEditorialReviewJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, UpdatesEditorialReview;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public Book $book,
        public EditorialReview $review,
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
                __('The editorial review could not continue because no configured AI provider is selected.'),
                EditorialReviewErrorCode::NoProvider,
            );

            return;
        }

        $setting->injectConfig();

        if (! $this->review->chapterNotes()->exists()) {
            $this->markReviewFailed($this->review, __('No chapter content available for editorial review.'), EditorialReviewErrorCode::NoContent);

            return;
        }

        try {
            $chapters = $this->book->chapters()
                ->orderBy('reader_order')
                ->get();

            $this->synthesizeSections($chapters);
            $this->generateExecutiveSummary();
        } catch (Throwable $e) {
            report($e);
            $this->markReviewFailedFromThrowable(
                $this->review,
                $e,
                $this->safeSynthesisFailureMessage(),
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception) {
            report($exception);
            $this->markReviewFailedFromThrowable(
                $this->review,
                $exception,
                $this->safeSynthesisFailureMessage(),
            );

            return;
        }

        $this->markReviewFailed(
            $this->review,
            $this->safeSynthesisFailureMessage(),
        );
    }

    /**
     * Phase 2 — synthesis: run EditorialSynthesisAgent per section. Sections
     * persisted by an earlier run are skipped, so a resumed review only pays
     * for the sections that are still missing.
     *
     * @param  Collection<int, Chapter>  $chapters
     */
    private function synthesizeSections(Collection $chapters): void
    {
        $this->review->update(['status' => 'synthesizing']);

        $chapterNotes = $this->review->chapterNotes()->with('chapter')->get();

        $synthesized = $this->review->sections()->pluck('type')->all();

        foreach (EditorialSectionType::cases() as $sectionType) {
            if (in_array($sectionType, $synthesized, true)) {
                continue;
            }

            $this->updateReviewProgress($this->review, 'synthesizing', current_section: $sectionType->value);

            $aggregatedData = $this->buildAggregatedDataForSection($sectionType, $chapters, $chapterNotes);

            $agent = new EditorialSynthesisAgent(
                book: $this->book,
                sectionType: $sectionType,
                aggregatedData: $aggregatedData,
            );

            $response = $agent->prompt('Synthesize the editorial review for this section.', timeout: 180);
            $result = $response->toArray();

            $section = $this->review->sections()->updateOrCreate(
                ['type' => $sectionType],
                [
                    'score' => $result['score'] ?? null,
                    'summary' => $result['summary'] ?? null,
                    'strengths' => $result['strengths'] ?? [],
                    'findings' => $result['findings'] ?? [],
                    'recommendations' => $result['recommendations'] ?? [],
                ],
            );

            $section->ensureFindingKeys();
        }
    }

    /**
     * Phase 3 — executive summary from all section results.
     */
    private function generateExecutiveSummary(): void
    {
        $this->updateReviewProgress($this->review, 'summarizing');

        $sections = $this->review->sections()->get();

        $summariesString = $sections->map(function ($section) {
            return "[{$section->type->value}] Score: {$section->score}/100\n{$section->summary}";
        })->implode("\n\n");

        $agent = new EditorialSummaryAgent(
            book: $this->book,
            sectionSummaries: $summariesString,
        );

        $response = $agent->prompt('Generate the executive summary.', timeout: 180);
        $result = $response->toArray();

        $this->review->update([
            'overall_score' => $result['overall_score'] ?? null,
            'executive_summary' => $result['executive_summary'] ?? null,
            'top_strengths' => $result['top_strengths'] ?? [],
            'top_improvements' => $result['top_improvements'] ?? [],
            'is_pre_editorial' => $result['is_pre_editorial'] ?? false,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Build aggregated data for a specific editorial section type.
     *
     * @param  Collection<int, Chapter>  $chapters
     * @param  Collection<int, EditorialReviewChapterNote>  $chapterNotes
     */
    private function buildAggregatedDataForSection(EditorialSectionType $sectionType, Collection $chapters, Collection $chapterNotes): string
    {
        $parts = [];

        match ($sectionType) {
            EditorialSectionType::Plot, EditorialSectionType::Characters => $this->appendAnalysisData($parts, $sectionType, $chapters, $chapterNotes),
            EditorialSectionType::Pacing => $this->appendPacingData($parts, $chapters, $chapterNotes),
            default => $this->appendEditorialNotesData($parts, $sectionType, $chapterNotes),
        };

        return implode("\n\n", $parts);
    }

    /**
     * Append analysis table data for Plot/Characters sections.
     *
     * @param  list<string>  $parts
     * @param  Collection<int, Chapter>  $chapters
     * @param  Collection<int, EditorialReviewChapterNote>  $chapterNotes
     */
    private function appendAnalysisData(array &$parts, EditorialSectionType $sectionType, Collection $chapters, Collection $chapterNotes): void
    {
        $relevantTypes = match ($sectionType) {
            EditorialSectionType::Plot => [AnalysisType::Plothole, AnalysisType::PlotDeviation],
            EditorialSectionType::Characters => [AnalysisType::CharacterConsistency],
            default => [],
        };

        $analyses = $this->book->analyses()
            ->whereIn('type', $relevantTypes)
            ->get();

        if ($analyses->isNotEmpty()) {
            $parts[] = "Manuscript analyses:\n".$analyses->map(function ($analysis) {
                $result = is_array($analysis->result) ? json_encode($analysis->result) : $analysis->result;

                return "[{$analysis->type->value}] Chapter {$analysis->chapter_id}: {$result}";
            })->implode("\n");
        }

        $this->appendChapterSummaries($parts, $chapters);
        $this->appendEditorialNotesData($parts, $sectionType, $chapterNotes);
    }

    /**
     * Append pacing-specific data from chapter columns.
     *
     * @param  list<string>  $parts
     * @param  Collection<int, Chapter>  $chapters
     * @param  Collection<int, EditorialReviewChapterNote>  $chapterNotes
     */
    private function appendPacingData(array &$parts, Collection $chapters, Collection $chapterNotes): void
    {
        $pacingData = $chapters->map(function (Chapter $chapter) {
            $line = "Ch{$chapter->reader_order} ({$chapter->title}):";
            $line .= " tension={$chapter->tension_score}";
            $line .= " pacing={$chapter->pacing_feel}";
            $line .= " micro_tension={$chapter->micro_tension_score}";

            return $line;
        })->implode("\n");

        if ($pacingData) {
            $parts[] = "Pacing data per chapter:\n{$pacingData}";
        }

        $this->appendEditorialNotesData($parts, EditorialSectionType::Pacing, $chapterNotes);
    }

    /**
     * Append editorial notes relevant to a section type.
     *
     * @param  list<string>  $parts
     * @param  Collection<int, EditorialReviewChapterNote>  $chapterNotes
     */
    private function appendEditorialNotesData(array &$parts, EditorialSectionType $sectionType, Collection $chapterNotes): void
    {
        if ($sectionType === EditorialSectionType::ChapterNotes) {
            $allNotes = $chapterNotes->map(function ($note) {
                $label = $this->chapterNoteLabel($note);

                return "{$label}: ".json_encode($note->notes);
            })->implode("\n");

            if ($allNotes) {
                $parts[] = "Per-chapter editorial notes:\n{$allNotes}";
            }

            return;
        }

        $noteKey = match ($sectionType) {
            EditorialSectionType::NarrativeVoice => 'narrative_voice',
            EditorialSectionType::Themes => 'themes',
            EditorialSectionType::SceneCraft => 'scene_craft',
            EditorialSectionType::ProseStyle => 'prose_style_patterns',
            default => null,
        };

        if ($noteKey === null) {
            return;
        }

        $relevantNotes = $chapterNotes->filter(fn ($note) => isset($note->notes[$noteKey]));

        if ($relevantNotes->isNotEmpty()) {
            $formatted = $relevantNotes->map(function ($note) use ($noteKey) {
                $label = $this->chapterNoteLabel($note);

                return "{$label}: ".json_encode($note->notes[$noteKey]);
            })->implode("\n");

            $parts[] = "Editorial notes ({$noteKey}):\n{$formatted}";
        }
    }

    /**
     * Build a human-readable label for a chapter note.
     */
    private function chapterNoteLabel(EditorialReviewChapterNote $note): string
    {
        $chapter = $note->chapter;

        return $chapter ? "Ch{$chapter->reader_order} ({$chapter->title})" : "Chapter {$note->chapter_id}";
    }

    /**
     * Append chapter summaries for context.
     *
     * @param  list<string>  $parts
     * @param  Collection<int, Chapter>  $chapters
     */
    private function appendChapterSummaries(array &$parts, Collection $chapters): void
    {
        $summaries = $chapters->filter(fn (Chapter $ch) => $ch->summary)
            ->map(fn (Chapter $ch) => "Ch{$ch->reader_order} ({$ch->title}): {$ch->summary}")
            ->implode("\n");

        if ($summaries) {
            $parts[] = "Chapter summaries:\n{$summaries}";
        }
    }

    private function safeSynthesisFailureMessage(): string
    {
        $section = $this->review->progress['current_section'] ?? null;

        if (is_string($section) && $section !== '') {
            return __('The review stopped while creating the :section section. Your completed work was saved.', [
                'section' => str_replace('_', ' ', $section),
            ]);
        }

        return __('The review stopped while creating the executive summary. Your completed work was saved.');
    }
}
