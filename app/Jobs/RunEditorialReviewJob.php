<?php

namespace App\Jobs;

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\EditorialNotesAgent;
use App\Ai\Agents\EditorialSummaryAgent;
use App\Ai\Agents\EditorialSynthesisAgent;
use App\Ai\Support\TextPrep;
use App\Enums\AnalysisType;
use App\Enums\EditorialSectionType;
use App\Jobs\Concerns\PersistsChapterAnalysis;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\EditorialReview;
use App\Models\EditorialReviewChapterNote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunEditorialReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, PersistsChapterAnalysis, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public Book $book,
        public EditorialReview $review,
    ) {}

    public function handle(): void
    {
        try {
            $setting = AiSetting::activeProvider();

            if (! $setting || ! $setting->isConfigured()) {
                $this->markFailed('No AI provider configured.');

                return;
            }

            $setting->injectConfig();

            $chapters = $this->book->chapters()
                ->with(['currentVersion', 'scenes', 'analyses'])
                ->orderBy('reader_order')
                ->get();

            $this->refreshStaleAnalyses($chapters);
            $this->gapFillChapters($chapters);

            if (! $this->review->chapterNotes()->exists()) {
                $this->markFailed(__('No chapter content available for editorial review.'));

                return;
            }

            $this->synthesizeSections($chapters);
            $this->generateExecutiveSummary();
        } catch (Throwable $e) {
            report($e);
            $this->markFailed($e->getMessage());
        }
    }

    /**
     * Phase 0 — Refresh stale chapter analyses.
     *
     * @param  Collection<int, Chapter>  $chapters
     */
    private function refreshStaleAnalyses(Collection $chapters): void
    {
        $this->review->update([
            'status' => 'analyzing',
            'started_at' => $this->review->started_at ?? now(),
        ]);

        $staleChapters = $chapters->filter(fn (Chapter $chapter) => $chapter->needsAiPreparation());

        if ($staleChapters->isEmpty()) {
            return;
        }

        $total = $staleChapters->count();
        $current = 0;

        foreach ($staleChapters as $chapter) {
            $current++;
            $this->updateProgress('refreshing', current_chapter: $current, total_chapters: $total);

            try {
                $content = $chapter->getFullContent();
                if (empty($content)) {
                    continue;
                }

                $capped = TextPrep::plainTextCapped($content);
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
    }

    /**
     * Phase 1 — Gap fill: run EditorialNotesAgent per chapter.
     *
     * @param  Collection<int, Chapter>  $chapters
     */
    private function gapFillChapters(Collection $chapters): void
    {
        $total = $chapters->count();
        $current = 0;
        $writingStyle = $this->book->writing_style_display;
        $characterData = $this->buildCharacterData();

        foreach ($chapters as $chapter) {
            $current++;
            $this->updateProgress('analyzing', current_chapter: $current, total_chapters: $total);

            try {
                $content = $chapter->getFullContent();
                if (empty($content)) {
                    continue;
                }

                $capped = TextPrep::plainTextCapped($content);

                $existingAnalysis = $this->buildExistingAnalysisContext($chapter);

                $agent = new EditorialNotesAgent(
                    book: $this->book,
                    existingAnalysis: $existingAnalysis,
                    writingStyle: $writingStyle,
                    characterData: $characterData,
                );

                $response = $agent->prompt($capped, timeout: 180);

                $this->review->chapterNotes()->create([
                    'chapter_id' => $chapter->id,
                    'notes' => $response->toArray(),
                ]);
            } catch (Throwable $e) {
                report($e);
                Log::warning("Editorial review: chapter gap-fill failed for chapter {$chapter->id}", [
                    'review_id' => $this->review->id,
                    'chapter_id' => $chapter->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Phase 2 — Synthesis: run EditorialSynthesisAgent per section.
     *
     * @param  Collection<int, Chapter>  $chapters
     */
    private function synthesizeSections(Collection $chapters): void
    {
        $this->review->update(['status' => 'synthesizing']);

        $chapterNotes = $this->review->chapterNotes()->with('chapter')->get();

        foreach (EditorialSectionType::cases() as $sectionType) {
            $this->updateProgress('synthesizing', current_section: $sectionType->value);

            $aggregatedData = $this->buildAggregatedDataForSection($sectionType, $chapters, $chapterNotes);

            $agent = new EditorialSynthesisAgent(
                book: $this->book,
                sectionType: $sectionType,
                aggregatedData: $aggregatedData,
            );

            $response = $agent->prompt('Synthesize the editorial review for this section.', timeout: 180);
            $result = $response->toArray();

            $section = $this->review->sections()->create([
                'type' => $sectionType,
                'score' => $result['score'] ?? null,
                'summary' => $result['summary'] ?? null,
                'findings' => $result['findings'] ?? [],
                'recommendations' => $result['recommendations'] ?? [],
            ]);

            $section->ensureFindingKeys();
        }
    }

    /**
     * Phase 3 — Executive summary from all section results.
     */
    private function generateExecutiveSummary(): void
    {
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

    /**
     * Update the review's progress JSON.
     */
    private function updateProgress(
        string $phase,
        ?int $current_chapter = null,
        ?int $total_chapters = null,
        ?string $current_section = null,
    ): void {
        $progress = ['phase' => $phase];

        if ($current_chapter !== null) {
            $progress['current_chapter'] = $current_chapter;
        }

        if ($total_chapters !== null) {
            $progress['total_chapters'] = $total_chapters;
        }

        if ($current_section !== null) {
            $progress['current_section'] = $current_section;
        }

        $this->review->update(['progress' => $progress]);
    }

    private function markFailed(string $message): void
    {
        $this->review->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception) {
            report($exception);
        }

        $this->markFailed($exception?->getMessage() ?? 'Unknown error');
    }
}
