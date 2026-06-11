<?php

namespace App\Ai\Tools;

use App\Ai\Support\TextPrep;
use App\Models\Book;
use App\Models\Chapter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RetrieveManuscriptContext implements Tool
{
    public function __construct(private int $bookId) {}

    public function description(): Stringable|string
    {
        return 'Retrieves manuscript context including characters, chapter summaries, plot points, and active chapter text for the current book. When a chapter_id is provided, also returns plot beats linked to that chapter (grouped by parent plot point) and wiki entries connected to that chapter (grouped by kind).';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'chapter_id' => $schema->integer()->nullable()->required(),
            'include_characters' => $schema->boolean()->nullable()->required(),
            'include_plot_points' => $schema->boolean()->nullable()->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->render(
            chapterId: $request['chapter_id'] ?? null,
            includeCharacters: $request['include_characters'] ?? true,
            includePlotPoints: $request['include_plot_points'] ?? true,
        );
    }

    /**
     * Render the manuscript context. Exposed separately so agents can inline
     * it into a prompt instead of spending a tool round trip on it.
     */
    public function render(?int $chapterId = null, bool $includeCharacters = true, bool $includePlotPoints = true): string
    {
        $book = Book::query()->findOrFail($this->bookId);
        $sections = [];

        $sections[] = "Book: {$book->title} by {$book->author} (Language: {$book->language})";

        if ($includeCharacters) {
            $characters = $book->characters()->get();
            if ($characters->isNotEmpty()) {
                $sections[] = "\n## Characters";
                foreach ($characters as $character) {
                    $aliases = $character->aliases ? ' (aliases: '.implode(', ', $character->aliases).')' : '';
                    $sections[] = "- {$character->name}{$aliases}: {$character->fullDescription()}";
                }
            }
        }

        if ($includePlotPoints) {
            $plotPoints = $book->plotPoints()->get();
            if ($plotPoints->isNotEmpty()) {
                $sections[] = "\n## Plot Points";
                foreach ($plotPoints as $point) {
                    $sections[] = "- [{$point->status->value}] {$point->title}: {$point->description}";
                }
            }
        }

        $chapters = $book->chapters()
            ->with('currentVersion')
            ->orderBy('reader_order')
            ->get();

        if ($chapters->isNotEmpty()) {
            $sections[] = "\n## Chapter Summaries";
            foreach ($chapters as $chapter) {
                $wordCount = $chapter->word_count;
                $summary = $chapter->summary ? " — {$chapter->summary}" : '';
                $hookInfo = $chapter->hook_score ? " [hook:{$chapter->hook_score}/10 ({$chapter->hook_type})]" : '';
                $tensionInfo = $chapter->tension_score ? " [tension:{$chapter->tension_score}/10]" : '';
                $sections[] = "- Ch{$chapter->reader_order}: {$chapter->title} ({$wordCount} words, {$chapter->status->value}){$tensionInfo}{$hookInfo}{$summary}";
            }
        }

        if ($chapterId) {
            $chapter = $book->chapters()
                ->with([
                    'currentVersion',
                    'beats.plotPoint',
                    'wikiEntries',
                ])
                ->find($chapterId);

            if ($chapter) {
                $sections = [
                    ...$sections,
                    ...$this->buildChapterPlotSection($chapter),
                    ...$this->buildChapterWikiSection($chapter),
                ];

                if ($chapter->currentVersion?->content) {
                    $sections[] = "\n## Active Chapter Text: {$chapter->title}";
                    $sections[] = TextPrep::plainTextCapped($chapter->currentVersion->content);
                }
            }
        }

        return implode("\n", $sections);
    }

    /**
     * @return array<int, string>
     */
    private function buildChapterPlotSection(Chapter $chapter): array
    {
        if ($chapter->beats->isEmpty()) {
            return [];
        }

        $lines = [
            "\n## Plot Beats for This Chapter",
            'Beats land in this chapter and carry plot intent — reference them when discussing what should happen here.',
        ];

        foreach ($chapter->beats->groupBy(fn ($beat) => $beat->plot_point_id) as $beatGroup) {
            $plotPoint = $beatGroup->first()->plotPoint;
            $description = $plotPoint->description ? ": {$plotPoint->description}" : '';
            $type = $plotPoint->type?->value ?? '—';
            $lines[] = "- Plot Point [{$type}/{$plotPoint->status->value}] {$plotPoint->title}{$description}";

            foreach ($beatGroup as $beat) {
                $beatDescription = $beat->description ? ": {$beat->description}" : '';
                $lines[] = "  - [{$beat->status->value}] {$beat->title}{$beatDescription}";
            }
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function buildChapterWikiSection(Chapter $chapter): array
    {
        if ($chapter->wikiEntries->isEmpty()) {
            return [];
        }

        $lines = [
            "\n## Wiki Entries for This Chapter",
            'World-building entries connected to this chapter — use them as ground truth for places, factions, items, and lore that appear here.',
        ];

        foreach ($chapter->wikiEntries->groupBy(fn ($entry) => $entry->kind->value) as $kindEntries) {
            $kind = $kindEntries->first()->kind;
            $lines[] = "### {$kind->pluralLabel()}";

            foreach ($kindEntries as $entry) {
                $type = $entry->type ? " ({$entry->type})" : '';
                $description = $entry->fullDescription();
                $descriptionPart = $description ? ": {$description}" : '';
                $lines[] = "- {$entry->name}{$type}{$descriptionPart}";
            }
        }

        return $lines;
    }
}
