<?php

namespace App\Ai\Tools\Plot\Concerns;

use App\Ai\Support\BeatEntityScanner;
use App\Models\Beat;
use App\Models\Character;
use App\Models\WikiEntry;

/**
 * Validate that a proposed chapter's character_ids / wiki_entry_ids are
 * non-empty whenever the chapter's beats reference known book entities.
 *
 * Existence-check only: the agent picks which specific entities to include;
 * this validator just ensures *something* was attempted when beats reference
 * book entities. False positives (agent disagrees with a regex hit) are fine
 * — agent supplies its own non-empty list and validation passes.
 */
trait ValidatesChapterEntityLinks
{
    /**
     * @param  list<array{title: string, beat_ids?: list<int>, character_ids?: list<int>, wiki_entry_ids?: list<int>}>  $chapters
     * @return string|null null if all valid; else a multi-chapter rejection message
     */
    protected function validateChapterEntityLinks(int $bookId, array $chapters): ?string
    {
        if ($chapters === []) {
            return null;
        }

        $allBeatIds = [];
        foreach ($chapters as $chapter) {
            foreach ($chapter['beat_ids'] ?? [] as $id) {
                $allBeatIds[] = (int) $id;
            }
        }
        $allBeatIds = array_values(array_unique($allBeatIds));

        if ($allBeatIds === []) {
            return null;
        }

        $beatRows = Beat::query()
            ->join('plot_points', 'plot_points.id', '=', 'beats.plot_point_id')
            ->whereIn('beats.id', $allBeatIds)
            ->where('plot_points.book_id', $bookId)
            ->get(['beats.id', 'beats.title', 'beats.description'])
            ->keyBy('id');

        if ($beatRows->isEmpty()) {
            return null;
        }

        $characters = Character::query()
            ->where('book_id', $bookId)
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => (int) $c->id, 'name' => (string) $c->name])
            ->all();

        $wikiEntries = WikiEntry::query()
            ->where('book_id', $bookId)
            ->get(['id', 'name'])
            ->map(fn ($w) => ['id' => (int) $w->id, 'name' => (string) $w->name])
            ->all();

        $scanner = new BeatEntityScanner;
        $errors = [];

        foreach ($chapters as $index => $chapter) {
            $beatIds = $chapter['beat_ids'] ?? [];
            $beatDescriptions = [];
            $beatTitlesByLocalIndex = [];

            foreach ($beatIds as $localIndex => $bid) {
                $row = $beatRows->get((int) $bid);
                $beatDescriptions[] = $row?->description ?? '';
                $beatTitlesByLocalIndex[$localIndex] = $row?->title ?? "beat #{$bid}";
            }

            $referencedChars = $scanner->findReferenced($beatDescriptions, $characters);
            $referencedWiki = $scanner->findReferenced($beatDescriptions, $wikiEntries);

            $charsListEmpty = ($chapter['character_ids'] ?? []) === [];
            $wikiListEmpty = ($chapter['wiki_entry_ids'] ?? []) === [];

            if ($referencedChars !== [] && $charsListEmpty) {
                $errors[] = $this->renderChapterError(
                    title: (string) $chapter['title'],
                    index: (int) $index,
                    field: 'character_ids',
                    matches: $referencedChars,
                    beatTitles: $beatTitlesByLocalIndex,
                );
            }

            if ($referencedWiki !== [] && $wikiListEmpty) {
                $errors[] = $this->renderChapterError(
                    title: (string) $chapter['title'],
                    index: (int) $index,
                    field: 'wiki_entry_ids',
                    matches: $referencedWiki,
                    beatTitles: $beatTitlesByLocalIndex,
                );
            }
        }

        if ($errors === []) {
            return null;
        }

        $header = "Chapter entity links missing — proposal rejected.\n\n";
        $footer = "\n\nRetry with the referenced entities included in `character_ids` / `wiki_entry_ids`. If a specific match is incidental (e.g. mentioned only in dialogue about a different scene), you may omit that id — but the lists cannot be empty when matches exist.";

        return $header.implode("\n\n", $errors).$footer;
    }

    /**
     * @param  list<array{id: int, name: string, beats: list<int>}>  $matches
     * @param  array<int, string>  $beatTitles  map of local beat index → beat title
     */
    private function renderChapterError(string $title, int $index, string $field, array $matches, array $beatTitles): string
    {
        $kind = $field === 'character_ids' ? 'characters' : 'wiki entries';
        $lines = ["Chapter \"{$title}\" (index {$index}):", "  - {$field} is empty, but beats reference these {$kind}:"];

        foreach ($matches as $match) {
            $beats = array_map(fn ($i) => '"'.($beatTitles[$i] ?? "beat #{$i}").'"', $match['beats']);
            $lines[] = "      • {$match['name']} (id={$match['id']}) — beat ".implode(', ', $beats);
        }

        return implode("\n", $lines);
    }
}
