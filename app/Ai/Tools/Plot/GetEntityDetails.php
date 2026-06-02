<?php

namespace App\Ai\Tools\Plot;

use App\Ai\Tools\Plot\Concerns\DecodesJsonPayload;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\PlotPoint;
use App\Models\WikiEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * On-demand deep read for entities the state block has truncated.
 *
 * The PlotCoachAgent state block carries id + name for every entity in the
 * book and a short body line per row (120 chars by default; 600–800 for
 * characters and wiki entries — the consistency anchors). For plot points,
 * beats, chapters — and the rare overflow case for characters / wiki —
 * deeper prose lives here. The coach should call this only when the
 * truncated line is genuinely insufficient.
 */
class GetEntityDetails implements Tool
{
    use DecodesJsonPayload;

    /**
     * Per-array hard cap. Keeps a single tool call from blowing context and
     * forces the coach to be deliberate about which entities it actually
     * needs to re-read.
     */
    private const MAX_IDS_PER_TYPE = 10;

    public function __construct(private int $bookId) {}

    public function description(): Stringable|string
    {
        return 'Fetches the full description of specific plot points, beats, chapters, characters, or wiki entries by id. Use ONLY when the truncated state-block line is insufficient — e.g. re-reading the prose of a beat before proposing what comes next, reconciling a lore rule whose body exceeded the per-row budget, or checking a character\'s wound before pushing back on a motivation. Do NOT call to refresh memory on entities the state block already shows in full. Pass each id list as a JSON-encoded array string, e.g. `plot_point_ids="[12,15]"`. Each list is capped at 10 ids.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'plot_point_ids' => $schema->string()->nullable()->required(),
            'beat_ids' => $schema->string()->nullable()->required(),
            'chapter_ids' => $schema->string()->nullable()->required(),
            'character_ids' => $schema->string()->nullable()->required(),
            'wiki_entry_ids' => $schema->string()->nullable()->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $book = Book::query()->find($this->bookId);

        if (! $book) {
            return "Error: book_id={$this->bookId} not found.";
        }

        $idLists = [];

        foreach (['plot_point_ids', 'beat_ids', 'chapter_ids', 'character_ids', 'wiki_entry_ids'] as $field) {
            $error = null;
            $idLists[$field] = $this->normalizeIdArray($request[$field] ?? null, $error);

            if ($error !== null) {
                return "Error: `{$field}` was not valid JSON ({$error}). Pass it as a JSON array of integers, e.g. `{$field}=\"[12,15]\"`.";
            }
        }

        foreach ($idLists as $name => $ids) {
            if (count($ids) > self::MAX_IDS_PER_TYPE) {
                return "Error: {$name} has more than ".self::MAX_IDS_PER_TYPE.' ids. Split into multiple calls.';
            }
        }

        $totalRequested = array_sum(array_map('count', $idLists));

        if ($totalRequested === 0) {
            return 'Error: pass at least one id in plot_point_ids, beat_ids, chapter_ids, character_ids, or wiki_entry_ids.';
        }

        $sections = [];

        if (! empty($idLists['plot_point_ids'])) {
            $sections[] = $this->renderPlotPoints($this->bookId, $idLists['plot_point_ids']);
        }

        if (! empty($idLists['beat_ids'])) {
            $sections[] = $this->renderBeats($this->bookId, $idLists['beat_ids']);
        }

        if (! empty($idLists['chapter_ids'])) {
            $sections[] = $this->renderChapters($this->bookId, $idLists['chapter_ids']);
        }

        if (! empty($idLists['character_ids'])) {
            $sections[] = $this->renderCharacters($this->bookId, $idLists['character_ids']);
        }

        if (! empty($idLists['wiki_entry_ids'])) {
            $sections[] = $this->renderWikiEntries($this->bookId, $idLists['wiki_entry_ids']);
        }

        $sections = array_values(array_filter($sections, static fn ($s) => $s !== ''));

        if (empty($sections)) {
            return 'No matching entities found for the given ids on this book.';
        }

        return implode("\n\n", $sections);
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function renderPlotPoints(int $bookId, array $ids): string
    {
        $rows = PlotPoint::query()
            ->where('book_id', $bookId)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get(['id', 'act_id', 'title', 'description', 'status']);

        if ($rows->isEmpty()) {
            return '';
        }

        $lines = ['## Plot points'];

        foreach ($rows as $row) {
            $status = $row->status?->value ?? 'unknown';
            $body = trim((string) $row->description);
            $lines[] = "\n### id={$row->id} act_id={$row->act_id} [{$status}] \"{$row->title}\"";
            $lines[] = $body !== '' ? $body : '_(no description)_';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function renderBeats(int $bookId, array $ids): string
    {
        $rows = Beat::query()
            ->join('plot_points', 'plot_points.id', '=', 'beats.plot_point_id')
            ->where('plot_points.book_id', $bookId)
            ->whereIn('beats.id', $ids)
            ->orderBy('beats.id')
            ->get(['beats.id', 'beats.plot_point_id', 'beats.title', 'beats.description', 'beats.status']);

        if ($rows->isEmpty()) {
            return '';
        }

        $lines = ['## Beats'];

        foreach ($rows as $row) {
            $status = $row->status?->value ?? 'unknown';
            $body = trim((string) $row->description);
            $lines[] = "\n### id={$row->id} plot_point_id={$row->plot_point_id} [{$status}] \"{$row->title}\"";
            $lines[] = $body !== '' ? $body : '_(no description)_';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function renderChapters(int $bookId, array $ids): string
    {
        $rows = Chapter::query()
            ->where('book_id', $bookId)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get(['id', 'title', 'summary']);

        if ($rows->isEmpty()) {
            return '';
        }

        $lines = ['## Chapters'];

        foreach ($rows as $row) {
            $body = trim((string) $row->summary);
            $lines[] = "\n### id={$row->id} \"{$row->title}\"";
            $lines[] = $body !== '' ? $body : '_(no summary)_';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function renderCharacters(int $bookId, array $ids): string
    {
        $rows = Character::query()
            ->where('book_id', $bookId)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get(['id', 'name', 'aliases', 'description', 'ai_description']);

        if ($rows->isEmpty()) {
            return '';
        }

        $lines = ['## Characters'];

        foreach ($rows as $row) {
            $aliases = ! empty($row->aliases) ? ' (aliases: '.implode(', ', $row->aliases).')' : '';
            $body = trim((string) $row->fullDescription());
            $lines[] = "\n### id={$row->id} \"{$row->name}\"{$aliases}";
            $lines[] = $body !== '' ? $body : '_(no description)_';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function renderWikiEntries(int $bookId, array $ids): string
    {
        $rows = WikiEntry::query()
            ->where('book_id', $bookId)
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get(['id', 'name', 'kind', 'description', 'ai_description', 'metadata']);

        if ($rows->isEmpty()) {
            return '';
        }

        $lines = ['## Wiki entries'];

        foreach ($rows as $row) {
            $kind = $row->kind?->value ?? 'unknown';
            $aliases = ! empty($row->metadata['aliases'] ?? null)
                ? ' (aliases: '.implode(', ', $row->metadata['aliases']).')'
                : '';
            $body = trim((string) $row->fullDescription());
            $lines[] = "\n### id={$row->id} [{$kind}] \"{$row->name}\"{$aliases}";
            $lines[] = $body !== '' ? $body : '_(no description)_';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIdArray(mixed $raw, ?string &$error = null): array
    {
        $decoded = $this->decodeJsonPayload($raw, $error);

        $ids = [];

        foreach ($decoded as $value) {
            if (is_int($value)) {
                $ids[] = $value;
            } elseif (is_string($value) && is_numeric($value)) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }
}
