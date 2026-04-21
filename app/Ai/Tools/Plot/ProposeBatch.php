<?php

namespace App\Ai\Tools\Plot;

use App\Models\Character;
use App\Models\WikiEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Staged preview of a proposed batch. Pure — never writes.
 *
 * The agent reads the returned markdown preview and pastes it (verbatim or
 * paraphrased) into chat. The user approves in conversation, then the agent
 * calls `ApplyPlotCoachBatch` with the same writes + summary.
 */
class ProposeBatch implements Tool
{
    public function description(): Stringable|string
    {
        return 'Presents a preview of writes you intend to make — characters, storylines, plot points, beats, wiki entries. Use this when the user has just agreed to something concrete, you have multiple coherent writes ready, and the conversation is at a natural resting point. The user will approve, edit, or reject in chat before anything is persisted.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'book_id' => $schema->integer()->required(),
            'writes' => $schema->array()->required(),
            'summary' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $bookId = $request['book_id'] ?? null;
        $writes = $request['writes'] ?? [];
        $summary = $request['summary'] ?? '';

        if (! is_array($writes) || empty($writes)) {
            return "Batch preview: (empty)\n\nSummary: {$summary}";
        }

        $grouped = [
            'character' => [],
            'wiki_entry' => [],
            'storyline' => [],
            'plot_point' => [],
            'beat' => [],
        ];

        foreach ($writes as $write) {
            if (! is_array($write) || empty($write['type']) || ! isset($grouped[$write['type']])) {
                continue;
            }
            $grouped[$write['type']][] = $write['data'] ?? [];
        }

        $duplicates = $this->detectDuplicates($bookId, $grouped);

        $sections = [];
        $sections[] = "## Proposed batch\n\n_{$summary}_";

        $labels = [
            'character' => 'Characters',
            'wiki_entry' => 'Wiki entries',
            'storyline' => 'Storylines',
            'plot_point' => 'Plot points',
            'beat' => 'Beats',
        ];

        foreach ($labels as $type => $label) {
            if (empty($grouped[$type])) {
                continue;
            }

            $sections[] = "### {$label}";
            foreach ($grouped[$type] as $data) {
                $line = '- '.$this->renderLine($type, $data);
                if ($this->isDuplicate($type, $data, $duplicates)) {
                    $line .= ' _(name already exists — will create a duplicate)_';
                }
                $sections[] = $line;
            }
        }

        $total = array_sum(array_map('count', $grouped));
        $sections[] = "\n_{$total} item".($total === 1 ? '' : 's').' — awaiting approval._';

        if ($duplicates['count'] > 0) {
            $sections[] = "_{$duplicates['count']} proposed name".($duplicates['count'] === 1 ? '' : 's').' already exist on this book. Consider asking the user whether to reuse an existing entity or confirm the duplicate.__';
        }

        $sections[] = $this->renderSentinel($writes, $summary);

        return implode("\n", $sections);
    }

    /**
     * Build a lookup of existing character + wiki-entry names for this book.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $grouped
     * @return array{characters: array<string, true>, wiki_entries: array<string, true>, count: int}
     */
    private function detectDuplicates(?int $bookId, array $grouped): array
    {
        $result = ['characters' => [], 'wiki_entries' => [], 'count' => 0];

        if (! $bookId) {
            return $result;
        }

        $characterNames = array_values(array_filter(array_map(
            fn ($c) => is_string($c['name'] ?? null) ? mb_strtolower(trim($c['name'])) : null,
            $grouped['character'] ?? []
        )));

        $wikiNames = array_values(array_filter(array_map(
            fn ($w) => is_string($w['name'] ?? null) ? mb_strtolower(trim($w['name'])) : null,
            $grouped['wiki_entry'] ?? []
        )));

        if ($characterNames) {
            $existing = Character::query()
                ->where('book_id', $bookId)
                ->pluck('name')
                ->map(fn ($n) => mb_strtolower(trim((string) $n)))
                ->all();
            foreach ($characterNames as $name) {
                if (in_array($name, $existing, true)) {
                    $result['characters'][$name] = true;
                    $result['count']++;
                }
            }
        }

        if ($wikiNames) {
            $existing = WikiEntry::query()
                ->where('book_id', $bookId)
                ->pluck('name')
                ->map(fn ($n) => mb_strtolower(trim((string) $n)))
                ->all();
            foreach ($wikiNames as $name) {
                if (in_array($name, $existing, true)) {
                    $result['wiki_entries'][$name] = true;
                    $result['count']++;
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{characters: array<string, true>, wiki_entries: array<string, true>, count: int}  $duplicates
     */
    private function isDuplicate(string $type, array $data, array $duplicates): bool
    {
        $name = $data['name'] ?? null;
        if (! is_string($name)) {
            return false;
        }

        $key = mb_strtolower(trim($name));

        return match ($type) {
            'character' => isset($duplicates['characters'][$key]),
            'wiki_entry' => isset($duplicates['wiki_entries'][$key]),
            default => false,
        };
    }

    /**
     * Renders a machine-readable sentinel block that the frontend parses out
     * of the assistant message to render the BatchProposalCard. The AI is
     * instructed (via agent persona) not to paraphrase this block.
     *
     * @param  array<int, array<string, mixed>>  $writes
     */
    private function renderSentinel(array $writes, string $summary): string
    {
        $payload = json_encode([
            'proposal_id' => (string) Str::uuid(),
            'writes' => $writes,
            'summary' => $summary,
        ], JSON_UNESCAPED_SLASHES);

        return "\n<!-- PLOT_COACH_BATCH_PROPOSAL\n{$payload}\n-->";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderLine(string $type, array $data): string
    {
        return match ($type) {
            'character' => $this->characterLine($data),
            'wiki_entry' => $this->wikiEntryLine($data),
            'storyline' => $this->storylineLine($data),
            'plot_point' => $this->plotPointLine($data),
            'beat' => $this->beatLine($data),
            default => '(unknown write)',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function characterLine(array $data): string
    {
        $name = $data['name'] ?? '(unnamed)';
        $desc = $data['ai_description'] ?? null;

        return $desc ? "{$name} — {$desc}" : (string) $name;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function wikiEntryLine(array $data): string
    {
        $name = $data['name'] ?? '(unnamed)';
        $kind = $data['kind'] ?? 'entry';
        $desc = $data['ai_description'] ?? null;

        $line = "[{$kind}] {$name}";

        return $desc ? "{$line} — {$desc}" : $line;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storylineLine(array $data): string
    {
        $name = $data['name'] ?? '(unnamed)';
        $type = $data['type'] ?? 'main';

        return "[{$type}] {$name}";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function plotPointLine(array $data): string
    {
        $title = $data['title'] ?? '(untitled)';
        $type = $data['type'] ?? null;
        $desc = $data['description'] ?? null;

        $line = $type ? "[{$type}] {$title}" : (string) $title;

        return $desc ? "{$line} — {$desc}" : $line;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function beatLine(array $data): string
    {
        $title = $data['title'] ?? '(untitled)';
        $desc = $data['description'] ?? null;

        return $desc ? "{$title} — {$desc}" : (string) $title;
    }
}
