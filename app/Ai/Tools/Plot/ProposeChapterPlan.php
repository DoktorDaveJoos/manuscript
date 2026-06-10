<?php

namespace App\Ai\Tools\Plot;

use App\Ai\Tools\Plot\Concerns\DecodesJsonPayload;
use App\Ai\Tools\Plot\Concerns\ValidatesChapterEntityLinks;
use App\Enums\PlotCoachProposalKind;
use App\Models\Chapter;
use App\Models\PlotCoachProposal;
use App\Models\PlotCoachSession;
use App\Models\Storyline;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Preview a chapter-stubbing plan for the current book. Pure — never writes.
 *
 * Mirrors `ProposeBatch`: emits a human-readable markdown preview plus a
 * machine-readable sentinel block that the frontend parses to render a
 * BatchProposalCard. Once the author approves in chat, the agent calls
 * `ApplyPlotCoachBatch` with writes of type `chapter` (optionally alongside
 * other types).
 *
 * Idempotency: the preview calls out which proposed chapter titles already
 * exist on the same storyline — those rows will be reused (beats re-attached)
 * rather than duplicated. Never silent rename, never silent delete of
 * pre-existing chapters.
 */
class ProposeChapterPlan implements Tool
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
        return 'Presents a preview of chapter stubs you intend to add — one per proposed chapter, fully wiring beats, the POV character, supporting characters, the act, and any locations / items / lore / organizations (wiki entries) the chapter touches. Use this once beats exist and the author has agreed to break structure into chapters. The author will approve in chat before anything is persisted. Additive only: chapters whose (storyline, title) already exist will be reused (beats / characters / wiki entries re-attached without detaching), never renamed or deleted. Pass `chapters` as a JSON-encoded string of an array of `{"title": string, "storyline_id": int, "act_id"?: int, "pov_character_id"?: int, "beat_ids"?: int[], "character_ids": int[], "wiki_entry_ids": int[]}` objects. When the storyline is created in the same approved batch (so its id does not exist yet), pass `"storyline_name": string` (the exact storyline name) instead of `storyline_id` — the server resolves it at apply time. `character_ids` and `wiki_entry_ids` are REQUIRED on every chapter — list every supporting character and every location/item/organization/lore concept whose name appears in the attached beats\' descriptions. POV is added to the supporting cast pivot automatically; do not repeat it in `character_ids`. Empty arrays (`[]`) are valid only when the beats reference no known entities; otherwise the tool will reject the proposal and you must retry with the missing entities included. Example chapter: `{"title": "Madeira: Apparat-Anflug", "storyline_id": 12, "act_id": 3, "pov_character_id": 44, "beat_ids": [88, 89], "character_ids": [42, 47], "wiki_entry_ids": [12, 18]}`.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'chapters' => $schema->string()->required(),
            'summary' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        // Bound at construction — a payload `book_id` is deliberately ignored
        // so the model cannot redirect a chapter plan to another book.
        $bookId = $this->bookId;
        $parseError = null;
        $chapters = $this->decodeJsonPayload($request['chapters'] ?? null, $parseError);
        $summary = (string) ($request['summary'] ?? '');

        if ($parseError !== null) {
            return "Chapter plan failed: `chapters` was not valid JSON ({$parseError}). Nothing persisted. Re-emit the chapters array — escape every \" inside string values as \\\" and every newline as \\n. Do not paraphrase the failure to the user; re-call ProposeChapterPlan with the corrected JSON.";
        }

        if (empty($chapters)) {
            return "Chapter plan preview: (empty)\n\nSummary: {$summary}";
        }

        $storylineIdsByName = $this->storylineIdsByName($bookId, $chapters);
        $existing = $this->existingChapterKeys($bookId, $chapters, $storylineIdsByName);

        $writes = [];
        $reusedTitles = [];
        $invalid = [];

        foreach ($chapters as $index => $chapter) {
            if (! is_array($chapter)) {
                $invalid[] = "chapter at index {$index}: not an object";

                continue;
            }

            $data = $this->normalizeChapter($chapter);

            if ($data === null) {
                $invalid[] = "chapter at index {$index}".(isset($chapter['title']) && is_string($chapter['title']) && trim($chapter['title']) !== '' ? " (\"{$chapter['title']}\")" : '')
                    .': '.$this->normalizationFailureReason($chapter);

                continue;
            }

            // Reuse detection needs a concrete storyline id; a storyline_name
            // pointing at a not-yet-created storyline can't collide.
            $resolvedStorylineId = $data['storyline_id']
                ?? ($storylineIdsByName[mb_strtolower((string) ($data['storyline_name'] ?? ''))] ?? null);

            $reused = $resolvedStorylineId !== null
                && isset($existing[$this->chapterKey((int) $resolvedStorylineId, (string) $data['title'])]);

            if ($reused) {
                $reusedTitles[] = $data['title'];
            }

            $writes[] = ['type' => 'chapter', 'data' => $data];
        }

        // Reject loudly rather than silently dropping chapters — a proposal
        // that quietly lost entries would be approved as if it were complete.
        if ($invalid !== []) {
            return "Chapter plan rejected — some chapters are malformed. Nothing persisted.\n\n- "
                .implode("\n- ", $invalid)
                ."\n\nEvery chapter needs a non-empty `title` and a numeric `storyline_id` (or `storyline_name` when the storyline is created in the same batch). Re-call ProposeChapterPlan with all chapters fixed.";
        }

        if ($bookId !== null) {
            $chapterDataForValidation = array_map(fn ($w) => $w['data'], $writes);
            $rejection = $this->validateChapterEntityLinks($bookId, $chapterDataForValidation);

            if ($rejection !== null) {
                return $rejection;
            }
        }

        // Compact on purpose: the chapter writes live ONCE in the sentinel
        // JSON below (the frontend renders the approval card from it, and it
        // is what the model re-reads on replay).
        $sections = [];
        $sections[] = "## Proposed chapter plan\n\n_{$summary}_";

        $total = count($writes);
        $sections[] = "_{$total} chapter".($total === 1 ? '' : 's').' — awaiting approval. The author sees the full preview card rendered from the sentinel below; do not restate its contents._';

        if ($reusedTitles !== []) {
            $sections[] = '_'.count($reusedTitles).' already exist on the matching storyline ('.implode(', ', $reusedTitles).') — those will be reused (beats re-linked), never renamed or deleted._';
        }

        $proposalId = $this->persistProposal($bookId, $writes, $summary);

        $sections[] = $this->renderSentinel($writes, $summary, $proposalId);

        return implode("\n", $sections);
    }

    /**
     * @param  array<int, array{type: string, data: array<string, mixed>}>  $writes
     */
    private function persistProposal(int $bookId, array $writes, string $summary): string
    {
        $session = $this->session ?? PlotCoachSession::activeForBook($bookId);

        if (! $session) {
            return (string) Str::uuid();
        }

        return PlotCoachProposal::record($session, PlotCoachProposalKind::ChapterPlan, $writes, $summary);
    }

    /**
     * Resolve the `storyline_name` references in a chapter list against the
     * book's existing storylines. Map of lowercased name → storyline id
     * (newest id wins). Names that don't match yet (storyline created in the
     * same batch) simply don't appear.
     *
     * @param  array<int, mixed>  $chapters
     * @return array<string, int>
     */
    private function storylineIdsByName(?int $bookId, array $chapters): array
    {
        if (! $bookId) {
            return [];
        }

        $names = [];

        foreach ($chapters as $chapter) {
            $name = is_array($chapter) ? ($chapter['storyline_name'] ?? null) : null;

            if (is_string($name) && trim($name) !== '') {
                $names[] = trim($name);
            }
        }

        if ($names === []) {
            return [];
        }

        return Storyline::query()
            ->where('book_id', $bookId)
            ->whereIn('name', array_values(array_unique($names)))
            ->orderBy('id')
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($s) => [mb_strtolower(trim((string) $s->name)) => (int) $s->id])
            ->all();
    }

    /**
     * Build a lookup of existing (storyline_id, normalized-title) keys so the
     * preview can flag reuse before the author approves.
     *
     * @param  array<int, mixed>  $chapters
     * @param  array<string, int>  $storylineIdsByName
     * @return array<string, true>
     */
    private function existingChapterKeys(?int $bookId, array $chapters, array $storylineIdsByName = []): array
    {
        if (! $bookId) {
            return [];
        }

        $storylineIds = array_values(array_unique([
            ...array_filter(array_map(
                fn ($c) => is_array($c) && isset($c['storyline_id']) ? (int) $c['storyline_id'] : null,
                $chapters,
            )),
            ...array_values($storylineIdsByName),
        ]));

        if (empty($storylineIds)) {
            return [];
        }

        $rows = Chapter::query()
            ->where('book_id', $bookId)
            ->whereIn('storyline_id', $storylineIds)
            ->get(['storyline_id', 'title']);

        $lookup = [];

        foreach ($rows as $row) {
            $lookup[$this->chapterKey((int) $row->storyline_id, (string) $row->title)] = true;
        }

        return $lookup;
    }

    private function chapterKey(int $storylineId, string $title): string
    {
        return $storylineId.'|'.mb_strtolower(trim($title));
    }

    /**
     * Human-readable reason why {@see normalizeChapter} returned null.
     *
     * @param  array<string, mixed>  $chapter
     */
    private function normalizationFailureReason(array $chapter): string
    {
        $title = $chapter['title'] ?? null;

        if (! is_string($title) || trim($title) === '') {
            return 'missing or empty `title`';
        }

        return 'missing `storyline_id` (or `storyline_name` for a storyline created in the same batch)';
    }

    /**
     * @param  array<string, mixed>  $chapter
     * @return array{title: string, storyline_id?: int, storyline_name?: string, act_id?: int, pov_character_id?: int, beat_ids?: list<int>, character_ids?: list<int>, wiki_entry_ids?: list<int>}|null
     */
    private function normalizeChapter(array $chapter): ?array
    {
        $title = $chapter['title'] ?? null;
        $storylineId = $chapter['storyline_id'] ?? null;
        $storylineName = $chapter['storyline_name'] ?? null;

        if (! is_string($title) || trim($title) === '') {
            return null;
        }

        $data = ['title' => trim($title)];

        if (is_numeric($storylineId)) {
            $data['storyline_id'] = (int) $storylineId;
        } elseif (is_string($storylineName) && trim($storylineName) !== '') {
            // Storyline created in the same batch — apply resolves the name
            // after the storyline write has persisted in the transaction.
            $data['storyline_name'] = trim($storylineName);
        } else {
            return null;
        }

        if (isset($chapter['act_id']) && is_numeric($chapter['act_id'])) {
            $data['act_id'] = (int) $chapter['act_id'];
        }

        if (isset($chapter['pov_character_id']) && is_numeric($chapter['pov_character_id'])) {
            $data['pov_character_id'] = (int) $chapter['pov_character_id'];
        }

        if (isset($chapter['beat_ids']) && is_array($chapter['beat_ids'])) {
            $data['beat_ids'] = array_values(array_unique(array_map('intval', $chapter['beat_ids'])));
        }

        if (isset($chapter['character_ids']) && is_array($chapter['character_ids'])) {
            $data['character_ids'] = array_values(array_unique(array_map('intval', $chapter['character_ids'])));
        }

        if (isset($chapter['wiki_entry_ids']) && is_array($chapter['wiki_entry_ids'])) {
            $data['wiki_entry_ids'] = array_values(array_unique(array_map('intval', $chapter['wiki_entry_ids'])));
        }

        return $data;
    }

    /**
     * @param  array<int, array{type: string, data: array<string, mixed>}>  $writes
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
