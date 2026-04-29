<?php

namespace App\Ai\Tools\Plot;

use App\Ai\Tools\Plot\Concerns\DecodesJsonPayload;
use App\Enums\PlotCoachProposalKind;
use App\Models\PlotCoachProposal;
use App\Models\PlotCoachSession;
use App\Services\PlotCoachBatchService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Commits a previously-proposed batch to the DB in a single transaction.
 *
 * Prefers `proposal_id` — the tool then looks the writes up in the
 * `plot_coach_proposals` table so the agent cannot fabricate a batch that
 * was never shown to the user. Falls back to a raw `writes` payload only
 * when no proposal_id is passed (legacy path).
 *
 * On failure the whole batch rolls back — the agent is expected to re-propose
 * with the conflict resolved.
 */
class ApplyPlotCoachBatch implements Tool
{
    use DecodesJsonPayload;

    public function __construct(
        private int $bookId,
        private PlotCoachBatchService $service = new PlotCoachBatchService,
    ) {}

    public function description(): Stringable|string
    {
        return 'Applies a previously-proposed batch to the current book. STRONGLY PREFERRED: pass `proposal_id` (the uuid from the last ProposeBatch/ProposeChapterPlan sentinel). The tool will look up the exact writes the user saw and apply them — you cannot fabricate items this way. `writes`/`summary` are a legacy fallback and ignored when `proposal_id` is set. The user must have explicitly approved in chat before you call this. On failure (name collision, FK mismatch) the entire batch rolls back and you should re-propose with the conflict resolved.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'proposal_id' => $schema->string()->nullable()->required(),
            'writes' => $schema->string()->nullable()->required(),
            'summary' => $schema->string()->nullable()->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $session = PlotCoachSession::activeForBook($this->bookId);

        if (! $session) {
            return "Batch failed: no active plot coach session for book {$this->bookId}. Nothing persisted.";
        }

        $proposalId = $this->trimOrNull($request['proposal_id'] ?? null);

        if ($proposalId !== null) {
            return $this->applyByProposalId($session, $proposalId);
        }

        $parseError = null;
        $writes = $this->decodeJsonPayload($request['writes'] ?? null, $parseError);
        $summary = (string) ($request['summary'] ?? '');

        if ($parseError !== null) {
            return "Batch failed: `writes` was not valid JSON ({$parseError}). Nothing persisted. Prefer passing `proposal_id` from the most recent ProposeBatch sentinel — that path skips the writes parsing entirely.";
        }

        if (empty($writes)) {
            return 'Batch failed: pass either proposal_id (preferred) or a non-empty writes array. Nothing persisted.';
        }

        return $this->applyWrites($session, $writes, $summary);
    }

    private function applyByProposalId(PlotCoachSession $session, string $publicId): string
    {
        $proposal = PlotCoachProposal::findForSession($session, $publicId);

        if (! $proposal) {
            return "Batch failed: proposal `{$publicId}` does not exist for this session. Nothing persisted. Re-propose instead of echoing old ids.";
        }

        if ($proposal->approved_at) {
            return "Batch already applied earlier (proposal `{$publicId}`). Nothing changed this turn.";
        }

        if ($proposal->cancelled_at) {
            return "Batch failed: proposal `{$publicId}` was cancelled. Nothing persisted.";
        }

        $writes = is_array($proposal->writes) ? $proposal->writes : [];
        $summary = (string) $proposal->summary;

        try {
            $batch = $this->service->apply($session, $writes, $summary);
        } catch (Throwable $e) {
            return "Batch failed: {$e->getMessage()}. Nothing persisted. Propose a revised batch.";
        }

        $proposal->update([
            'approved_at' => now(),
            'applied_batch_id' => $batch->id,
        ]);

        $count = count($batch->payload['writes'] ?? []);
        $kind = $proposal->kind instanceof PlotCoachProposalKind ? $proposal->kind->value : (string) $proposal->kind;

        return "Applied batch #{$batch->id} ({$kind}): {$summary}. {$count} item".($count === 1 ? '' : 's').' written.';
    }

    /**
     * @param  array<int, array<string, mixed>>  $writes
     */
    private function applyWrites(PlotCoachSession $session, array $writes, string $summary): string
    {
        try {
            $batch = $this->service->apply($session, $writes, $summary);
        } catch (Throwable $e) {
            return "Batch failed: {$e->getMessage()}. Nothing persisted. Propose a revised batch.";
        }

        $count = count($batch->payload['writes'] ?? []);

        return "Applied batch #{$batch->id}: {$summary}. {$count} item".($count === 1 ? '' : 's').' written.';
    }

    private function trimOrNull(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);

        return $trimmed === '' ? null : $trimmed;
    }
}
