<?php

namespace App\Ai\Tools\Plot;

use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetPlotBoardState implements Tool
{
    public function __construct(private int $bookId) {}

    public function description(): Stringable|string
    {
        return 'Retrieves the current plot board state for the book — acts, plot points, beats, storylines, and existing characters. Call this when you need to reason about structure or recent writes.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'include_characters' => $schema->boolean()->nullable()->required(),
            'include_storylines' => $schema->boolean()->nullable()->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $includeCharacters = $request['include_characters'] ?? true;
        $includeStorylines = $request['include_storylines'] ?? true;

        $book = Book::query()
            ->with([
                'acts' => fn ($q) => $q->orderBy('sort_order'),
                'plotPoints' => fn ($q) => $q->orderBy('sort_order'),
                'plotPoints.beats',
                'storylines' => fn ($q) => $q->orderBy('sort_order'),
                'characters',
            ])
            ->findOrFail($this->bookId);

        $sections = [];

        $sections[] = '## Book';
        $genre = $book->genre?->label() ?? '(not set)';
        $target = $book->target_word_count ? number_format($book->target_word_count).' words' : '(not set)';
        $premise = $book->premise ?: '(not set)';
        $sections[] = "- Title: {$book->title}";
        $sections[] = "- Author: {$book->author}";
        $sections[] = "- Language: {$book->language}";
        $sections[] = "- Genre: {$genre}";
        $sections[] = "- Target length: {$target}";
        $sections[] = "- Premise: {$premise}";

        if ($includeStorylines) {
            $sections[] = "\n## Storylines";
            if ($book->storylines->isEmpty()) {
                $sections[] = '- (none)';
            } else {
                foreach ($book->storylines as $storyline) {
                    $type = $storyline->type?->value ?? 'unspecified';
                    $sections[] = "- id={$storyline->id} [{$type}] {$storyline->name}";
                }
            }
        }

        $sections[] = "\n## Structure";
        if ($book->acts->isEmpty()) {
            $sections[] = '- (no acts yet)';
        } else {
            $plotPointsByAct = $book->plotPoints->groupBy('act_id');

            foreach ($book->acts as $act) {
                $sections[] = "\n### id={$act->id} Act {$act->number}: {$act->title}";
                $actPlotPoints = $plotPointsByAct->get($act->id, collect());

                if ($actPlotPoints->isEmpty()) {
                    $sections[] = '- (no plot points)';

                    continue;
                }

                foreach ($actPlotPoints as $plotPoint) {
                    $status = $plotPoint->status?->value ?? 'unknown';
                    $description = $plotPoint->description ? " — {$plotPoint->description}" : '';
                    $sections[] = "- id={$plotPoint->id} [{$status}] {$plotPoint->title}{$description}";

                    foreach ($plotPoint->beats as $beat) {
                        $beatStatus = $beat->status?->value ?? 'unknown';
                        $beatDescription = $beat->description ? " — {$beat->description}" : '';
                        $sections[] = "  - id={$beat->id} [{$beatStatus}] {$beat->title}{$beatDescription}";
                    }
                }
            }

            // Plot points with no act
            $orphanPoints = $plotPointsByAct->get(null, collect());
            if ($orphanPoints->isNotEmpty()) {
                $sections[] = "\n### Unassigned plot points";
                foreach ($orphanPoints as $plotPoint) {
                    $status = $plotPoint->status?->value ?? 'unknown';
                    $sections[] = "- id={$plotPoint->id} [{$status}] {$plotPoint->title}";
                }
            }
        }

        if ($includeCharacters) {
            $sections[] = "\n## Characters";
            if ($book->characters->isEmpty()) {
                $sections[] = '- (none)';
            } else {
                foreach ($book->characters as $character) {
                    $aliases = ! empty($character->aliases) ? ' (aliases: '.implode(', ', $character->aliases).')' : '';
                    $description = $character->description ? ": {$character->description}" : '';
                    $sections[] = "- id={$character->id} {$character->name}{$aliases}{$description}";
                }
            }
        }

        $sections[] = "\n_Use the `id` values when proposing updates (e.g. `{\"type\":\"plot_point\",\"data\":{\"id\":42,...}}`)._";

        return implode("\n", $sections);
    }
}
