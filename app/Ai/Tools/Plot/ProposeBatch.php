<?php

namespace App\Ai\Tools\Plot;

use App\Ai\Tools\Plot\Concerns\DecodesJsonPayload;
use App\Enums\PlotCoachProposalKind;
use App\Models\Act;
use App\Models\Beat;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\PlotCoachProposal;
use App\Models\PlotCoachSession;
use App\Models\PlotPoint;
use App\Models\Storyline;
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
    use CoercesBookId, DecodesJsonPayload;

    public function description(): Stringable|string
    {
        return 'Presents a preview of writes you intend to make — characters, storylines, acts, plot points, beats, wiki entries, a patch of intake-stage book fields (premise, target_word_count, genre) via a `book_update` write, a session-level update (stage transition, coaching_mode) via a `session_update` write, or a soft-delete via a `delete` write of shape `{"type":"delete","data":{"target":"<character|wiki_entry|storyline|plot_point|beat|chapter|act>","id":<int>}}`. Use this when the user has just agreed to something concrete, you have one or more coherent writes ready, and the conversation is at a natural resting point. The user will approve, edit, or reject in chat before anything is persisted. Deletes are reversible via undo (rows are soft-deleted, not destroyed). Pass `writes` as a JSON-encoded string of an array of `{"type": string, "data": object}` objects, e.g. `[{"type":"act","data":{"number":1,"title":"The Controlled Error","description":"Lab accident sets the story in motion."}},{"type":"session_update","data":{"stage":"plotting"}}]`.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'book_id' => $schema->integer()->required(),
            'writes' => $schema->string()->required(),
            'summary' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $bookId = $this->coerceBookId($request['book_id'] ?? null);
        $parseError = null;
        $writes = $this->decodeJsonPayload($request['writes'] ?? null, $parseError);
        $summary = $request['summary'] ?? '';

        if ($parseError !== null) {
            return "Batch failed: `writes` was not valid JSON ({$parseError}). Nothing persisted. Re-emit the writes array — escape every \" inside string values as \\\" and every newline as \\n. Do not paraphrase the failure to the user; re-call ProposeBatch with the corrected JSON.";
        }

        if (empty($writes)) {
            return "Batch preview: (empty)\n\nSummary: {$summary}";
        }

        $writes = $this->enrichWrites($bookId, $writes);

        $grouped = [
            'book_update' => [],
            'session_update' => [],
            'character' => [],
            'wiki_entry' => [],
            'storyline' => [],
            'act' => [],
            'plot_point' => [],
            'beat' => [],
            'chapter' => [],
            'delete' => [],
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
            'book_update' => 'Book details',
            'session_update' => 'Session',
            'character' => 'Characters',
            'wiki_entry' => 'Wiki entries',
            'storyline' => 'Storylines',
            'act' => 'Acts',
            'plot_point' => 'Plot points',
            'beat' => 'Beats',
            'chapter' => 'Chapters',
            'delete' => 'Removals',
        ];

        foreach ($labels as $type => $label) {
            if (empty($grouped[$type])) {
                continue;
            }

            $sections[] = "### {$label}";
            foreach ($grouped[$type] as $data) {
                $prefix = match (true) {
                    $type === 'delete' => '- _Delete_ ',
                    isset($data['id']) => '- _Update_ ',
                    default => '- ',
                };
                $line = $prefix.$this->renderLine($type, $data, $bookId);
                if ($this->isDuplicate($type, $data, $duplicates)) {
                    $line .= ' _(name already exists — will create a duplicate)_';
                }
                $sections[] = $line;
            }
        }

        $total = array_sum(array_map('count', $grouped));
        $sections[] = "\n_{$total} item".($total === 1 ? '' : 's').' — awaiting approval._';

        if ($duplicates['count'] > 0) {
            $sections[] = "_{$duplicates['count']} proposed name".($duplicates['count'] === 1 ? '' : 's').' already exist on this book. Consider asking the user whether to reuse an existing entity or confirm the duplicate._';
        }

        $proposalId = $this->persistProposal($bookId, $writes, $summary);

        $sections[] = $this->renderSentinel($writes, $summary, $proposalId);

        return implode("\n", $sections);
    }

    /**
     * Persist the proposal so the controller can apply it deterministically on
     * approval. Returns the public uuid embedded in the sentinel.
     *
     * Silently hands out a throwaway uuid ONLY when no book_id was passed —
     * this preserves the "preview without persisting" path used by unit
     * tests. When a real numeric book_id is passed but no active session
     * exists we persist nothing and still return a disposable uuid so the
     * preview renders; approval will fail-loudly with "proposal does not
     * exist" instead of silently creating orphaned rows.
     *
     * @param  array<int, array<string, mixed>>  $writes
     */
    private function persistProposal(mixed $bookId, array $writes, string $summary): string
    {
        $bookId = $this->coerceBookId($bookId);

        if ($bookId === null) {
            return (string) Str::uuid();
        }

        $session = PlotCoachSession::activeForBook($bookId);

        if (! $session) {
            return (string) Str::uuid();
        }

        return PlotCoachProposal::record($session, PlotCoachProposalKind::Batch, $writes, $summary);
    }

    /**
     * Walk the writes once and inject `_existing_*` hint fields on every
     * update (writes carrying an `id`). The frontend uses these to render
     * the entity's current name + type when the agent's update payload
     * doesn't restate them — without this an "id-only" patch shows up as
     * "(unnamed)" in the preview card.
     *
     * Lookups are batched per type. Ignored keys on apply (the writers read
     * known fields by name), so this is preview-only metadata.
     *
     * @param  array<int, array<string, mixed>>  $writes
     * @return array<int, array<string, mixed>>
     */
    private function enrichWrites(?int $bookId, array $writes): array
    {
        if ($bookId === null) {
            return $writes;
        }

        $idsByType = [];

        foreach ($writes as $w) {
            if (! is_array($w) || empty($w['type']) || ! is_array($w['data'] ?? null)) {
                continue;
            }
            if (! isset($w['data']['id']) || ! is_numeric($w['data']['id'])) {
                continue;
            }
            $idsByType[$w['type']][] = (int) $w['data']['id'];
        }

        if ($idsByType === []) {
            return $writes;
        }

        $resolved = [];

        foreach ($idsByType as $type => $ids) {
            $ids = array_values(array_unique($ids));
            $resolved[$type] = $this->resolveEntities($type, $ids, $bookId);
        }

        return array_map(function ($w) use ($resolved) {
            if (! is_array($w) || empty($w['type']) || ! is_array($w['data'] ?? null)) {
                return $w;
            }
            $type = $w['type'];
            if (! isset($w['data']['id']) || ! isset($resolved[$type])) {
                return $w;
            }
            $id = (int) $w['data']['id'];
            $hit = $resolved[$type][$id] ?? null;
            if (! $hit) {
                return $w;
            }
            $w['data'] = array_merge($hit, $w['data']);

            return $w;
        }, $writes);
    }

    /**
     * Batch-fetch hint metadata for one type's ids. Returns a map keyed by id.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, string|null>>
     */
    private function resolveEntities(string $type, array $ids, int $bookId): array
    {
        $rows = match ($type) {
            'character' => Character::query()
                ->whereIn('id', $ids)
                ->where('book_id', $bookId)
                ->get(['id', 'name'])
                ->mapWithKeys(fn ($c) => [(int) $c->id => ['_existing_name' => (string) $c->name]])
                ->all(),
            'wiki_entry' => WikiEntry::query()
                ->whereIn('id', $ids)
                ->where('book_id', $bookId)
                ->get(['id', 'name', 'kind'])
                ->mapWithKeys(fn ($w) => [(int) $w->id => [
                    '_existing_name' => (string) $w->name,
                    '_existing_kind' => $w->kind?->value,
                ]])
                ->all(),
            'storyline' => Storyline::query()
                ->whereIn('id', $ids)
                ->where('book_id', $bookId)
                ->get(['id', 'name', 'type'])
                ->mapWithKeys(fn ($s) => [(int) $s->id => [
                    '_existing_name' => (string) $s->name,
                    '_existing_type' => $s->type?->value,
                ]])
                ->all(),
            'act' => Act::query()
                ->whereIn('id', $ids)
                ->where('book_id', $bookId)
                ->get(['id', 'title', 'number'])
                ->mapWithKeys(fn ($a) => [(int) $a->id => [
                    '_existing_name' => (string) $a->title,
                    '_existing_number' => $a->number !== null ? (string) $a->number : null,
                ]])
                ->all(),
            'plot_point' => PlotPoint::query()
                ->whereIn('id', $ids)
                ->where('book_id', $bookId)
                ->get(['id', 'title', 'type'])
                ->mapWithKeys(fn ($p) => [(int) $p->id => [
                    '_existing_name' => (string) $p->title,
                    '_existing_type' => $p->type?->value,
                ]])
                ->all(),
            'beat' => Beat::query()
                ->join('plot_points', 'plot_points.id', '=', 'beats.plot_point_id')
                ->whereIn('beats.id', $ids)
                ->where('plot_points.book_id', $bookId)
                ->get(['beats.id', 'beats.title'])
                ->mapWithKeys(fn ($b) => [(int) $b->id => ['_existing_name' => (string) $b->title]])
                ->all(),
            'chapter' => Chapter::query()
                ->whereIn('id', $ids)
                ->where('book_id', $bookId)
                ->get(['id', 'title'])
                ->mapWithKeys(fn ($c) => [(int) $c->id => ['_existing_name' => (string) $c->title]])
                ->all(),
            default => [],
        };

        return $rows;
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
        // Updates target an existing row by id — by definition not a duplicate.
        if (isset($data['id'])) {
            return false;
        }

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
     * of the assistant message to render the BatchProposalCard. The
     * `proposal_id` matches the row in `plot_coach_proposals` so the
     * controller can look it up on approval.
     *
     * @param  array<int, array<string, mixed>>  $writes
     */
    private function renderSentinel(array $writes, string $summary, string $proposalId): string
    {
        $payload = json_encode([
            'proposal_id' => $proposalId,
            'writes' => $writes,
            'summary' => $summary,
        ], JSON_UNESCAPED_SLASHES);

        return "\n<!-- PLOT_COACH_BATCH_PROPOSAL\n{$payload}\n-->";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderLine(string $type, array $data, ?int $bookId = null): string
    {
        return match ($type) {
            'character' => $this->characterLine($data),
            'wiki_entry' => $this->wikiEntryLine($data),
            'storyline' => $this->storylineLine($data),
            'act' => $this->actLine($data),
            'plot_point' => $this->plotPointLine($data),
            'beat' => $this->beatLine($data),
            'chapter' => $this->chapterLine($data, $bookId),
            'book_update' => $this->bookUpdateLine($data),
            'session_update' => $this->sessionUpdateLine($data),
            'delete' => $this->deleteLine($data, $bookId),
            default => '(unknown write)',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function chapterLine(array $data, ?int $bookId = null): string
    {
        $title = $this->resolveDisplayName($data, 'title');
        $id = $data['id'] ?? null;

        $head = match (true) {
            $title !== null => $title,
            $id !== null => "(chapter id={$id})",
            default => '(untitled)',
        };

        $bits = [];
        if (! empty($data['storyline_id'])) {
            $bits[] = 'storyline → '.$this->labelFor('storyline', (int) $data['storyline_id'], $bookId);
        }
        if (! empty($data['pov_character_id'])) {
            $bits[] = 'POV → '.$this->labelFor('character', (int) $data['pov_character_id'], $bookId);
        }
        if (! empty($data['act_id'])) {
            $bits[] = 'act → '.$this->labelFor('act', (int) $data['act_id'], $bookId);
        }
        if (array_key_exists('beat_ids', $data) && is_array($data['beat_ids'])) {
            $bits[] = count($data['beat_ids']).' beat'.(count($data['beat_ids']) === 1 ? '' : 's');
        }

        return $bits === [] ? $head : $head.' — '.implode(' · ', $bits);
    }

    private function labelFor(string $target, int $id, ?int $bookId): string
    {
        if ($bookId !== null) {
            $name = $this->resolveTargetName($target, $id, $bookId);
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return "id={$id}";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function deleteLine(array $data, ?int $bookId): string
    {
        $target = isset($data['target']) ? (string) $data['target'] : '?';
        $id = isset($data['id']) ? (int) $data['id'] : null;

        if ($id === null) {
            return "[{$target}] (missing id)";
        }

        $name = $bookId !== null ? $this->resolveTargetName($target, $id, $bookId) : null;

        return $name !== null
            ? "[{$target}] {$name} (id={$id})"
            : "[{$target}] id={$id}";
    }

    private function resolveTargetName(string $target, int $id, int $bookId): ?string
    {
        return match ($target) {
            'character' => Character::query()->where('id', $id)->where('book_id', $bookId)->value('name'),
            'wiki_entry' => WikiEntry::query()->where('id', $id)->where('book_id', $bookId)->value('name'),
            'storyline' => Storyline::query()->where('id', $id)->where('book_id', $bookId)->value('name'),
            'act' => Act::query()->where('id', $id)->where('book_id', $bookId)->value('title'),
            'plot_point' => PlotPoint::query()->where('id', $id)->where('book_id', $bookId)->value('title'),
            'beat' => Beat::query()
                ->join('plot_points', 'plot_points.id', '=', 'beats.plot_point_id')
                ->where('beats.id', $id)
                ->where('plot_points.book_id', $bookId)
                ->value('beats.title'),
            'chapter' => Chapter::query()->where('id', $id)->where('book_id', $bookId)->value('title'),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function actLine(array $data): string
    {
        $id = $data['id'] ?? null;
        $title = $this->resolveDisplayName($data, 'title');
        $number = isset($data['number'])
            ? (int) $data['number']
            : (isset($data['_existing_number']) ? (int) $data['_existing_number'] : null);
        $desc = $data['description'] ?? null;

        $head = match (true) {
            $title !== null => $number !== null ? "Act {$number}: {$title}" : $title,
            $number !== null => "Act {$number}",
            $id !== null => "(act id={$id})",
            default => '(untitled)',
        };

        return $desc ? "{$head} — {$desc}" : $head;
    }

    /**
     * Pick the best display name from the write payload: the agent-supplied
     * field first, then the `_existing_name` hint added by `enrichWrites`.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveDisplayName(array $data, string $primary): ?string
    {
        $value = $data[$primary] ?? null;
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }
        $existing = $data['_existing_name'] ?? null;
        if (is_string($existing) && trim($existing) !== '') {
            return $existing;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sessionUpdateLine(array $data): string
    {
        $parts = [];

        if (array_key_exists('stage', $data)) {
            $parts[] = 'stage: '.((string) ($data['stage'] ?? '(cleared)'));
        }

        if (array_key_exists('coaching_mode', $data)) {
            $mode = $data['coaching_mode'];
            $parts[] = 'coaching mode: '.($mode === null || $mode === '' ? '(cleared)' : (string) $mode);
        }

        return $parts === [] ? '(no fields)' : implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function bookUpdateLine(array $data): string
    {
        $parts = [];

        if (array_key_exists('premise', $data)) {
            $premise = trim((string) ($data['premise'] ?? ''));
            $parts[] = 'premise: '.($premise === '' ? '(cleared)' : $premise);
        }

        if (array_key_exists('target_word_count', $data)) {
            $target = $data['target_word_count'];
            $parts[] = 'target length: '.($target === null || $target === '' ? '(cleared)' : number_format((int) $target).' words');
        }

        if (array_key_exists('genre', $data)) {
            $genre = $data['genre'];
            $parts[] = 'genre: '.($genre === null || $genre === '' ? '(cleared)' : (string) $genre);
        }

        return $parts === [] ? '(no fields)' : implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function characterLine(array $data): string
    {
        $name = $this->resolveDisplayName($data, 'name') ?? '(unnamed)';
        $desc = $data['ai_description'] ?? null;

        return $desc ? "{$name} — {$desc}" : $name;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function wikiEntryLine(array $data): string
    {
        $name = $this->resolveDisplayName($data, 'name') ?? '(unnamed)';
        $kind = $data['kind'] ?? $data['_existing_kind'] ?? 'entry';
        $desc = $data['ai_description'] ?? null;

        $line = "[{$kind}] {$name}";

        return $desc ? "{$line} — {$desc}" : $line;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storylineLine(array $data): string
    {
        $id = $data['id'] ?? null;
        $name = $this->resolveDisplayName($data, 'name');
        $type = $data['type'] ?? $data['_existing_type'] ?? null;

        $head = match (true) {
            $name !== null => $name,
            $id !== null => "(storyline id={$id})",
            default => '(unnamed)',
        };

        return $type ? "[{$type}] {$head}" : $head;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function plotPointLine(array $data): string
    {
        $title = $this->resolveDisplayName($data, 'title') ?? '(untitled)';
        $type = $data['type'] ?? $data['_existing_type'] ?? null;
        $desc = $data['description'] ?? null;

        $line = $type ? "[{$type}] {$title}" : $title;

        return $desc ? "{$line} — {$desc}" : $line;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function beatLine(array $data): string
    {
        $title = $this->resolveDisplayName($data, 'title') ?? '(untitled)';
        $desc = $data['description'] ?? null;

        return $desc ? "{$title} — {$desc}" : $title;
    }
}
