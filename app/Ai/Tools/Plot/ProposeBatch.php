<?php

namespace App\Ai\Tools\Plot;

use App\Ai\Tools\Plot\Concerns\DecodesJsonPayload;
use App\Ai\Tools\Plot\Concerns\ValidatesChapterEntityLinks;
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
 * The tool output is passed through to the frontend, which renders the
 * approval card from the machine-readable sentinel block. The user approves
 * via the card (applied server-side) or in free text, in which case the agent
 * calls `ApplyPlotCoachBatch` with the sentinel's `proposal_id`.
 */
class ProposeBatch implements Tool
{
    use DecodesJsonPayload, ValidatesChapterEntityLinks;

    /**
     * The session is bound by the agent so proposals land on the conversation
     * being streamed — even when it is not the book's active session. Falls
     * back to the active session for direct construction (tests, legacy).
     */
    public function __construct(
        public int $bookId,
        private ?PlotCoachSession $session = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Presents a preview of writes you intend to make — characters, storylines, acts, plot points, beats, wiki entries, chapters (fully wired with storyline, beats, POV character, supporting characters via REQUIRED `character_ids`, locations/items/lore via REQUIRED `wiki_entry_ids`, and act — both lists must include every entity whose name appears in the attached beats; empty lists are valid only when no entities are referenced), a patch of intake-stage book fields (premise, target_word_count, genre) via a `book_update` write, a session-level update (stage transition, coaching_mode) via a `session_update` write, or a soft-delete via a `delete` write of shape `{"type":"delete","data":{"target":"<character|wiki_entry|storyline|plot_point|beat|chapter|act>","id":<int>}}`. Use this when the user has just agreed to something concrete, you have one or more coherent writes ready, and the conversation is at a natural resting point. The user will approve, edit, or reject in chat before anything is persisted. Deletes are reversible via undo (rows are soft-deleted, not destroyed). Pass `writes` as a JSON-encoded string of an array of `{"type": string, "data": object}` objects, e.g. `[{"type":"act","data":{"number":1,"title":"The Controlled Error","description":"Lab accident sets the story in motion."}},{"type":"session_update","data":{"stage":"plotting"}}]`.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'writes' => $schema->string()->required(),
            'summary' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        // The book is bound at construction. A payload `book_id` (older
        // conversations replaying a tool schema that still carried it) is
        // deliberately ignored — the model must not be able to redirect a
        // proposal to another book.
        $bookId = $this->bookId;
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

        if ($bookId !== null) {
            $chapterWrites = [];
            foreach ($writes as $write) {
                if (is_array($write) && ($write['type'] ?? null) === 'chapter' && is_array($write['data'] ?? null)) {
                    // Skip update writes — they target an existing chapter, the
                    // beats may already be linked, and the agent isn't adding
                    // new beats. Only validate creates (no `id` key).
                    if (! isset($write['data']['id'])) {
                        $chapterWrites[] = $write['data'];
                    }
                }
            }

            if ($chapterWrites !== []) {
                $rejection = $this->validateChapterEntityLinks($bookId, $chapterWrites);

                if ($rejection !== null) {
                    return $rejection;
                }
            }
        }

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

        // Reject unknown types up front. Anything that slips past the preview
        // is persisted into the proposal verbatim and would make the whole
        // batch throw at approval time — AFTER the user said yes.
        $unknownTypes = [];

        foreach ($writes as $write) {
            $type = is_array($write) ? ($write['type'] ?? null) : null;

            if (! is_string($type) || ! isset($grouped[$type])) {
                $unknownTypes[] = is_string($type) ? $type : '(missing type)';
            }
        }

        if ($unknownTypes !== []) {
            return 'Batch rejected: unknown write type'.(count($unknownTypes) === 1 ? '' : 's').' '
                .implode(', ', array_map(fn ($t) => "`{$t}`", array_unique($unknownTypes)))
                .'. Valid types: '.implode(', ', array_keys($grouped))
                .'. Nothing persisted. Re-call ProposeBatch with corrected types.';
        }

        foreach ($writes as $write) {
            $grouped[$write['type']][] = $write['data'] ?? [];
        }

        $duplicates = $this->detectDuplicates($bookId, $grouped);

        // Compact on purpose: the writes live ONCE in the sentinel JSON below
        // (the frontend renders the approval card from it, and it is what the
        // model re-reads on replay). A per-item markdown rendering would be
        // a second copy of the same data in every replayed turn.
        $sections = [];
        $sections[] = "## Proposed batch\n\n_{$summary}_";

        $total = count($writes);
        $sections[] = "_{$total} item".($total === 1 ? '' : 's').' — awaiting approval. The author sees the full preview card rendered from the sentinel below; do not restate its contents._';

        if ($duplicates['count'] > 0) {
            $sections[] = "_{$duplicates['count']} proposed name".($duplicates['count'] === 1 ? ' already exists' : 's already exist')
                .' on this book ('.implode(', ', $duplicates['names']).'). Ask the author whether to reuse the existing entity, rename, or confirm the duplicate before applying._';
        }

        $proposalId = $this->persistProposal($bookId, $writes, $summary);

        $sections[] = $this->renderSentinel($writes, $summary, $proposalId);

        return implode("\n", $sections);
    }

    /**
     * Persist the proposal so the controller can apply it deterministically on
     * approval. Returns the public uuid embedded in the sentinel.
     *
     * When no active session exists for the book we persist nothing and still
     * return a disposable uuid so the preview renders; approval will
     * fail-loudly with "proposal does not exist" instead of silently creating
     * orphaned rows.
     *
     * @param  array<int, array<string, mixed>>  $writes
     */
    private function persistProposal(int $bookId, array $writes, string $summary): string
    {
        $session = $this->session ?? PlotCoachSession::activeForBook($bookId);

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
     * Proposed character / wiki-entry names that already exist on this book.
     * Creates only — updates target an existing row by id and are by
     * definition not duplicates. Names keep their proposed casing so the
     * agent can raise them with the author verbatim.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $grouped
     * @return array{names: list<string>, count: int}
     */
    private function detectDuplicates(?int $bookId, array $grouped): array
    {
        $result = ['names' => [], 'count' => 0];

        if (! $bookId) {
            return $result;
        }

        $lookups = [
            'character' => Character::class,
            'wiki_entry' => WikiEntry::class,
        ];

        foreach ($lookups as $type => $modelClass) {
            $proposed = [];

            foreach ($grouped[$type] ?? [] as $data) {
                if (isset($data['id']) || ! is_string($data['name'] ?? null) || trim($data['name']) === '') {
                    continue;
                }
                $proposed[mb_strtolower(trim($data['name']))] = trim($data['name']);
            }

            if ($proposed === []) {
                continue;
            }

            $existing = $modelClass::query()
                ->where('book_id', $bookId)
                ->pluck('name')
                ->map(fn ($n) => mb_strtolower(trim((string) $n)))
                ->all();

            foreach ($proposed as $key => $original) {
                if (in_array($key, $existing, true)) {
                    $result['names'][] = $original;
                    $result['count']++;
                }
            }
        }

        return $result;
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
}
