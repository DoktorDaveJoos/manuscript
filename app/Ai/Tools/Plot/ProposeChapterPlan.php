<?php

namespace App\Ai\Tools\Plot;

use App\Ai\Tools\Plot\Concerns\CoercesBookId;
use App\Ai\Tools\Plot\Concerns\DecodesJsonPayload;
use App\Enums\PlotCoachProposalKind;
use App\Models\Chapter;
use App\Models\PlotCoachProposal;
use App\Models\PlotCoachSession;
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
    use CoercesBookId, DecodesJsonPayload;

    public function description(): Stringable|string
    {
        return 'Presents a preview of chapter stubs you intend to add — one per proposed chapter, optionally linking beats, a POV character, and an act. Use this once beats exist and the author has agreed to break structure into chapters. The author will approve in chat before anything is persisted. Additive only: chapters whose (storyline, title) already exist will be reused, never renamed or deleted. Pass `chapters` as a JSON-encoded string of an array of `{"title": string, "storyline_id": int, "act_id"?: int, "pov_character_id"?: int, "beat_ids"?: int[]}` objects.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'book_id' => $schema->integer()->required(),
            'chapters' => $schema->string()->required(),
            'summary' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $bookId = $this->coerceBookId($request['book_id'] ?? null);
        $parseError = null;
        $chapters = $this->decodeJsonPayload($request['chapters'] ?? null, $parseError);
        $summary = (string) ($request['summary'] ?? '');

        if ($parseError !== null) {
            return "Chapter plan failed: `chapters` was not valid JSON ({$parseError}). Nothing persisted. Re-emit the chapters array — escape every \" inside string values as \\\" and every newline as \\n. Do not paraphrase the failure to the user; re-call ProposeChapterPlan with the corrected JSON.";
        }

        if (empty($chapters)) {
            return "Chapter plan preview: (empty)\n\nSummary: {$summary}";
        }

        $existing = $this->existingChapterKeys($bookId, $chapters);

        $writes = [];
        $lines = [];
        $reusedCount = 0;

        foreach ($chapters as $chapter) {
            if (! is_array($chapter)) {
                continue;
            }

            $data = $this->normalizeChapter($chapter);

            if ($data === null) {
                continue;
            }

            $key = $this->chapterKey((int) $data['storyline_id'], (string) $data['title']);
            $reused = isset($existing[$key]);

            if ($reused) {
                $reusedCount++;
            }

            $lines[] = '- '.$this->renderLine($data, $reused);
            $writes[] = ['type' => 'chapter', 'data' => $data];
        }

        $sections = [];
        $sections[] = "## Proposed chapter plan\n\n_{$summary}_";
        $sections[] = '### Chapters';

        foreach ($lines as $line) {
            $sections[] = $line;
        }

        $total = count($writes);
        $sections[] = "\n_{$total} chapter".($total === 1 ? '' : 's').' — awaiting approval._';

        if ($reusedCount > 0) {
            $sections[] = "_{$reusedCount} already exist on the matching storyline — those will be reused (beats re-linked), never renamed or deleted._";
        }

        $proposalId = $this->persistProposal($bookId, $writes, $summary);

        $sections[] = $this->renderSentinel($writes, $summary, $proposalId);

        return implode("\n", $sections);
    }

    /**
     * @param  array<int, array{type: string, data: array<string, mixed>}>  $writes
     */
    private function persistProposal(?int $bookId, array $writes, string $summary): string
    {
        $session = is_int($bookId) ? PlotCoachSession::activeForBook($bookId) : null;

        if (! $session) {
            return (string) Str::uuid();
        }

        return PlotCoachProposal::record($session, PlotCoachProposalKind::ChapterPlan, $writes, $summary);
    }

    /**
     * Build a lookup of existing (storyline_id, normalized-title) keys so the
     * preview can flag reuse before the author approves.
     *
     * @param  array<int, mixed>  $chapters
     * @return array<string, true>
     */
    private function existingChapterKeys(?int $bookId, array $chapters): array
    {
        if (! $bookId) {
            return [];
        }

        $storylineIds = array_values(array_unique(array_filter(array_map(
            fn ($c) => is_array($c) && isset($c['storyline_id']) ? (int) $c['storyline_id'] : null,
            $chapters,
        ))));

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
     * @param  array<string, mixed>  $chapter
     * @return array{title: string, storyline_id: int, act_id?: int, pov_character_id?: int, beat_ids?: list<int>}|null
     */
    private function normalizeChapter(array $chapter): ?array
    {
        $title = $chapter['title'] ?? null;
        $storylineId = $chapter['storyline_id'] ?? null;

        if (! is_string($title) || trim($title) === '' || ! is_numeric($storylineId)) {
            return null;
        }

        $data = [
            'title' => trim($title),
            'storyline_id' => (int) $storylineId,
        ];

        if (isset($chapter['act_id']) && is_numeric($chapter['act_id'])) {
            $data['act_id'] = (int) $chapter['act_id'];
        }

        if (isset($chapter['pov_character_id']) && is_numeric($chapter['pov_character_id'])) {
            $data['pov_character_id'] = (int) $chapter['pov_character_id'];
        }

        if (isset($chapter['beat_ids']) && is_array($chapter['beat_ids'])) {
            $data['beat_ids'] = array_values(array_unique(array_map('intval', $chapter['beat_ids'])));
        }

        return $data;
    }

    /**
     * @param  array{title: string, storyline_id: int, act_id?: int, pov_character_id?: int, beat_ids?: list<int>}  $data
     */
    private function renderLine(array $data, bool $reused): string
    {
        $parts = [$data['title']];

        $meta = [];

        if (! empty($data['beat_ids'])) {
            $count = count($data['beat_ids']);
            $meta[] = $count.' beat'.($count === 1 ? '' : 's');
        }

        if (! empty($data['pov_character_id'])) {
            $meta[] = 'POV #'.$data['pov_character_id'];
        }

        if (! empty($data['act_id'])) {
            $meta[] = 'act #'.$data['act_id'];
        }

        if ($meta) {
            $parts[] = '('.implode(', ', $meta).')';
        }

        $line = implode(' ', $parts);

        if ($reused) {
            $line .= ' _(existing — will reuse)_';
        }

        return $line;
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
