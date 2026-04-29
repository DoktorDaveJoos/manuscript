<?php

namespace App\Http\Controllers;

use App\Ai\Agents\PlotCoachAgent;
use App\Ai\Support\PlotCoachWireSignals;
use App\Enums\PlotCoachSessionStatus;
use App\Enums\PlotCoachStage;
use App\Http\Controllers\Concerns\StreamsConversation;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\PlotCoachBatch;
use App\Models\PlotCoachProposal;
use App\Models\PlotCoachSession;
use App\Services\PlotCoachBatchService;
use App\Services\PlotCoachSessionSummarizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PlotCoachController extends Controller
{
    use StreamsConversation;

    public function stream(Request $request, Book $book): JsonResponse|StreamedResponse
    {
        return $this->streamChat(function () use ($request, $book) {
            $this->ensureAiConfigured();

            $request->validate([
                'message' => ['required', 'string', 'max:4000'],
                'session_id' => ['nullable', 'integer'],
            ]);

            $session = $this->resolveSession($request, $book);

            $agent = new PlotCoachAgent($book, $session);
            $agent->continue($session->agent_conversation_id, $request->user() ?? (object) ['id' => 0]);

            $rawMessage = (string) $request->input('message');

            $message = $this->handleApprovalSignals($session, $rawMessage);

            $message = $this->prependBoardChangesNote($session, $message);

            $message = $this->prependArchiveSummaryOnFirstTurn($session, $message);

            // Pick the cheapest provider model for trivial turns (system
            // acks, free-text approvals/cancels/undos). All other turns
            // pass null so the SDK uses #[UseSmartestModel] as configured
            // on the agent. See PlotCoachAgent::isTrivialTurn for the
            // classification rules.
            $stream = $agent->stream($message, model: $agent->modelForTurn($message));

            // Clear queued board changes + accrue per-session token usage only
            // after the stream completes successfully. On failure, pending
            // changes stay queued for the next turn.
            $this->attachPostStreamHooks($stream, $session);

            return $this->streamWithConversationId(
                $stream,
                $session->agent_conversation_id,
            );
        });
    }

    public function sessionIndex(Book $book): JsonResponse
    {
        $sessions = PlotCoachSession::query()
            ->where('book_id', $book->id)
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'status',
                'stage',
                'user_turn_count',
                'input_tokens',
                'output_tokens',
                'cost_cents',
                'archived_at',
                'created_at',
                'updated_at',
            ]);

        return response()->json($sessions);
    }

    public function sessionShow(Book $book, PlotCoachSession $session): JsonResponse
    {
        abort_unless($session->book_id === $book->id, 404);

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $session->agent_conversation_id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['role', 'content', 'tool_results'])
            ->reverse()
            ->map(fn ($m) => [
                'role' => $m->role,
                'content' => $this->mergeProposalToolResults(
                    (string) $m->role,
                    $this->sanitizeUserFacingContent((string) $m->role, (string) $m->content),
                    $m->tool_results,
                ),
            ])
            ->reject(fn ($m) => $m['role'] === 'user' && $m['content'] === '')
            ->values();

        return response()->json([
            'id' => $session->id,
            'book_id' => $session->book_id,
            'status' => $session->status,
            'stage' => $session->stage,
            'coaching_mode' => $session->coaching_mode,
            'decisions' => $session->decisions,
            'pending_board_changes' => $session->pending_board_changes,
            'agent_conversation_id' => $session->agent_conversation_id,
            'archived_at' => $session->archived_at,
            'archive_summary' => $session->archive_summary,
            'user_turn_count' => $session->user_turn_count,
            'parent_session_id' => $session->parent_session_id,
            'input_tokens' => $session->input_tokens,
            'output_tokens' => $session->output_tokens,
            'cost_cents' => $session->cost_cents,
            'created_at' => $session->created_at,
            'updated_at' => $session->updated_at,
            'messages' => $messages,
            'proposal_states' => $this->proposalStatesForSession($session),
        ]);
    }

    /**
     * Map every proposal in this session to its current state so the frontend
     * can render approval cards with the correct chrome on reload.
     *
     * @return array<string, string> uuid → "pending" | "approved" | "cancelled" | "reverted"
     */
    private function proposalStatesForSession(PlotCoachSession $session): array
    {
        $rows = PlotCoachProposal::query()
            ->where('plot_coach_proposals.session_id', $session->id)
            ->leftJoin('plot_coach_batches', 'plot_coach_batches.id', '=', 'plot_coach_proposals.applied_batch_id')
            ->orderBy('plot_coach_proposals.id')
            ->get([
                'plot_coach_proposals.public_id',
                'plot_coach_proposals.approved_at',
                'plot_coach_proposals.cancelled_at',
                'plot_coach_batches.reverted_at as batch_reverted_at',
            ]);

        $states = [];

        foreach ($rows as $row) {
            $states[(string) $row->public_id] = match (true) {
                $row->batch_reverted_at !== null => 'reverted',
                $row->approved_at !== null => 'approved',
                $row->cancelled_at !== null => 'cancelled',
                default => 'pending',
            };
        }

        return $states;
    }

    public function sessionArchive(Book $book, PlotCoachSession $session): Response
    {
        abort_unless($session->book_id === $book->id, 404);

        if ($session->status === PlotCoachSessionStatus::Archived) {
            return response()->noContent();
        }

        $session->load('book');

        $summary = (new PlotCoachSessionSummarizer)->buildArchiveSummary($session);

        $session->update([
            'status' => PlotCoachSessionStatus::Archived,
            'archived_at' => now(),
            'archive_summary' => $summary,
        ]);

        return response()->noContent();
    }

    public function sessionExport(Book $book, PlotCoachSession $session): Response
    {
        abort_unless($session->book_id === $book->id, 404);

        $session->load('book');

        $markdown = (new PlotCoachSessionSummarizer)->buildTranscriptMarkdown($session);

        $filename = 'plot-coach-session-'.$session->id.'.md';

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function resolveSession(Request $request, Book $book): PlotCoachSession
    {
        $sessionId = $request->input('session_id');

        if ($sessionId) {
            $session = PlotCoachSession::query()
                ->where('book_id', $book->id)
                ->find($sessionId);

            abort_unless($session, 404);

            return $session;
        }

        $session = PlotCoachSession::query()
            ->where('book_id', $book->id)
            ->active()
            ->first();

        if ($session) {
            return $session;
        }

        $conversationId = resolve(ConversationStore::class)->storeConversation(
            $request->user()?->id,
            Str::limit($request->input('message'), 100, preserveWords: true),
        );

        $parentSessionId = PlotCoachSession::query()
            ->where('book_id', $book->id)
            ->where('status', PlotCoachSessionStatus::Archived)
            ->orderByDesc('archived_at')
            ->value('id');

        return PlotCoachSession::query()->create([
            'book_id' => $book->id,
            'agent_conversation_id' => $conversationId,
            'status' => PlotCoachSessionStatus::Active,
            'stage' => PlotCoachStage::Intake,
            'coaching_mode' => null,
            'decisions' => [],
            'pending_board_changes' => [],
            'parent_session_id' => $parentSessionId,
        ]);
    }

    /**
     * Intercept structured APPROVE:batch / CANCEL:batch / UNDO:last signals
     * from the frontend and execute them server-side before the LLM runs.
     * Returns the original message unchanged if no signal matched, otherwise
     * prepends a `[system: ...]` note describing the outcome so the agent
     * can acknowledge and continue naturally.
     */
    private function handleApprovalSignals(PlotCoachSession $session, string $message): string
    {
        $trimmed = trim($message);

        $note = null;

        if (preg_match(PlotCoachWireSignals::PATTERN_APPROVE, $trimmed, $m)) {
            $note = $this->applyApproval($session, $m[1]);
        } elseif (preg_match(PlotCoachWireSignals::PATTERN_CANCEL, $trimmed, $m)) {
            $note = $this->applyCancel($session, $m[1]);
        } elseif (preg_match(PlotCoachWireSignals::PATTERN_UNDO_PROPOSAL, $trimmed, $m)) {
            $note = $this->applyProposalUndo($session, $m[1]);
        } elseif (preg_match(PlotCoachWireSignals::PATTERN_UNDO_LAST, $trimmed)) {
            $note = $this->applyUndo($session);
        }

        return $note === null ? $message : "{$note}\n\n{$message}";
    }

    private function applyApproval(PlotCoachSession $session, string $publicId): string
    {
        $proposal = PlotCoachProposal::findForSession($session, $publicId);

        if (! $proposal) {
            return '[system: An approval came in for a proposal that no longer exists. Nothing was applied. Move on in one short line without naming any id — offer to re-propose if it makes sense.]';
        }

        if ($proposal->approved_at) {
            return '[system: This proposal was already applied earlier. No changes this turn. Continue forward in one short line — do not re-acknowledge or re-list anything.]';
        }

        if ($proposal->cancelled_at) {
            return '[system: This proposal was previously cancelled. Nothing applied this turn. Continue forward in one short line without re-mentioning it.]';
        }

        try {
            $batch = app(PlotCoachBatchService::class)->apply(
                $session,
                is_array($proposal->writes) ? $proposal->writes : [],
                $proposal->summary,
            );
        } catch (Throwable $e) {
            return '[system: The batch failed to apply. Error: '.$e->getMessage().'. Nothing was persisted. Diagnose and AUTO-CORRECT if you can:'
                .' (a) duplicate name → rename the entity or drop it from the writes and propose again;'
                .' (b) invalid enum value → swap to the correct one and propose again;'
                .' (c) cross-book id / id from another book → look up the correct id with `LookupExistingEntities` or `GetPlotBoardState` and propose again;'
                .' (d) unresolved `plot_point_title` → propose the plot_point first or fix the spelling and propose again;'
                .' (e) empty patch → drop the no-op write.'
                .' Call ProposeBatch again with the fix in the SAME turn — do not wait for the author to retry. If the failure needs the author to choose (e.g. genuine ambiguity about which entity they meant), ask in ONE short line. Never echo ids, batch numbers, or proposal uuids.]';
        }

        $proposal->update([
            'approved_at' => now(),
            'applied_batch_id' => $batch->id,
        ]);

        return '[system: The batch was applied. The author already sees the confirmation in the approval card above this turn. STRICT rules for your reply: (1) do NOT call ApplyPlotCoachBatch again — it is done; (2) do NOT echo or mention the proposal id, batch number, or any uuid; (3) do NOT re-list or re-summarize what was saved — the card already shows it; (4) reply with ONE short forward-looking line only — a next question, the next thread to pick up, or a tiny sign of life ("Drin. Was kommt als Nächstes?"). No acknowledgment summary. No preview text. The card is the source of truth.]';
    }

    private function applyCancel(PlotCoachSession $session, string $publicId): string
    {
        $proposal = PlotCoachProposal::findForSession($session, $publicId);

        if ($proposal && ! $proposal->approved_at && ! $proposal->cancelled_at) {
            $proposal->update(['cancelled_at' => now()]);
        }

        return '[system: The proposal was cancelled. Do NOT echo the proposal id, do not ask why. Reply with ONE short line and move on — offer a different angle or ask what the author wants to try instead.]';
    }

    private function applyUndo(PlotCoachSession $session): string
    {
        try {
            $reverted = app(PlotCoachBatchService::class)->undo($session);
        } catch (Throwable $e) {
            return '[system: Undo failed: '.$e->getMessage().'. Nothing changed. Reply with one short line.]';
        }

        if (! $reverted) {
            return '[system: Nothing to undo — no recent applied batches in this session. Reply with one short line and move on.]';
        }

        return '[system: The last batch was reverted. Do NOT echo batch numbers or re-list the removed items. Reply with ONE short line — offer to re-propose only if that fits the thread, otherwise ask what the author wants next.]';
    }

    private function applyProposalUndo(PlotCoachSession $session, string $publicId): string
    {
        $proposal = PlotCoachProposal::findForSession($session, $publicId);

        if (! $proposal || ! $proposal->applied_batch_id) {
            return '[system: Per-card undo came in for a proposal that was never applied. Nothing changed. Reply with one short line and move on.]';
        }

        $batch = PlotCoachBatch::query()->find($proposal->applied_batch_id);

        if (! $batch) {
            return '[system: Per-card undo: the batch record is missing. Nothing changed. Reply with one short line.]';
        }

        if ($batch->reverted_at) {
            return '[system: Per-card undo: this card was already reverted. No change this turn. Reply with one short line.]';
        }

        try {
            app(PlotCoachBatchService::class)->undoBatch($batch);
        } catch (Throwable $e) {
            return '[system: Per-card undo failed: '.$e->getMessage().'. Nothing changed. Reply with one short line.]';
        }

        return '[system: The card above was reverted. The author already sees the new state. Do NOT echo batch numbers or re-list what was removed. Reply with ONE short forward-looking line.]';
    }

    /**
     * Render the pending_board_changes queue into a system-framed note and
     * prepend it to the user's message for this turn only. The SDK does not
     * expose a per-turn system-message mechanism, so we frame it inline.
     */
    private function prependBoardChangesNote(PlotCoachSession $session, string $message): string
    {
        $changes = $session->pending_board_changes ?? [];

        if (empty($changes)) {
            return $message;
        }

        $note = $this->formatBoardChangesNote($changes);

        return "[system: {$note}]\n\n{$message}";
    }

    /**
     * @param  array<int, array{kind?: string, type?: string, id?: int|string, summary?: string, at?: string}>  $changes
     */
    private function formatBoardChangesNote(array $changes): string
    {
        $count = count($changes);

        if ($count > 10) {
            $tail = array_slice($changes, -3);
            $recent = implode('; ', array_map(
                fn ($c) => (string) ($c['summary'] ?? ''),
                $tail,
            ));

            return "Board changes: {$count} total — most recent: {$recent}";
        }

        $lines = array_map(
            fn ($c) => '- '.((string) ($c['summary'] ?? '')),
            $changes,
        );

        return "Board changes since last turn:\n".implode("\n", $lines);
    }

    /**
     * Attach completion hooks to the streamable response so that per-session
     * usage counters are updated and the board-change queue is flushed only
     * after the stream iterates to completion. On error, both remain intact.
     */
    private function attachPostStreamHooks(StreamableAgentResponse $stream, PlotCoachSession $session): void
    {
        $sessionId = $session->id;

        $stream->then(function ($response) use ($sessionId) {
            /** @var PlotCoachSession|null $fresh */
            $fresh = PlotCoachSession::query()->find($sessionId);

            if (! $fresh) {
                return;
            }

            $usage = $response->usage ?? null;

            if ($usage) {
                $fresh->increment('input_tokens', (int) ($usage->promptTokens ?? 0));
                $fresh->increment('output_tokens', (int) ($usage->completionTokens ?? 0));
            }

            $fresh->update(['pending_board_changes' => []]);
            $fresh->increment('user_turn_count');
        });
    }

    /**
     * If this session was spawned off an archived predecessor, prepend the
     * predecessor's archive summary as a [system: ...] note into the very
     * first user turn so the new agent is not cold.
     */
    private function prependArchiveSummaryOnFirstTurn(PlotCoachSession $session, string $message): string
    {
        if (($session->user_turn_count ?? 0) > 0 || ! $session->parent_session_id) {
            return $message;
        }

        $summary = PlotCoachSession::query()
            ->whereKey($session->parent_session_id)
            ->value('archive_summary');

        if (! $summary || trim($summary) === '') {
            return $message;
        }

        return "[system: Handoff from previous plot coach session:\n{$summary}]\n\n{$message}";
    }

    /**
     * Strip internal scaffolding from a user turn before exposing it to the
     * chat UI. Internal scaffolding includes:
     *  - `[system: ...]` notes the controller prepended to the turn (approval
     *    acknowledgments, board-change digests, archive summaries, etc.)
     *  - the bare wire signals `APPROVE:batch:<uuid>`, `CANCEL:batch:<uuid>`,
     *    `UNDO:last` sent when the user presses a button on an approval card.
     *
     * Returns an empty string for turns that are nothing but scaffolding; the
     * caller is expected to drop those messages from the rendered history.
     */
    private function sanitizeUserFacingContent(string $role, string $content): string
    {
        if ($role !== 'user') {
            return $content;
        }

        // Strip every leading `[system: ...]` block. There may be more than
        // one because the controller stacks several of them (approval +
        // board changes + first-turn archive summary) onto the same turn.
        while (str_starts_with(ltrim($content), PlotCoachWireSignals::SYSTEM_PREFIX)) {
            $content = ltrim($content);
            $end = strpos($content, ']');
            if ($end === false) {
                break;
            }
            $content = ltrim(substr($content, $end + 1));
        }

        // Hide bare wire signals emitted by the approval buttons.
        if (preg_match(PlotCoachWireSignals::PATTERN_ANY, trim($content))) {
            return '';
        }

        return $content;
    }

    /**
     * When rehydrating session history, the AI SDK stores the assistant's
     * free-text content and each tool call's result in separate columns.
     * ProposeBatch / ProposeChapterPlan emit the sentinel block in their
     * `result` field — if the model obeyed the "don't paraphrase tool
     * output" rule its `content` won't include the sentinel. Splice those
     * two ProposeBatch-family tool results into the content here so the
     * frontend's sentinel parser has something to work with on reload.
     */
    private function mergeProposalToolResults(string $role, string $content, mixed $rawToolResults): string
    {
        if ($role !== 'assistant' || ! $rawToolResults) {
            return $content;
        }

        $decoded = is_string($rawToolResults) ? json_decode($rawToolResults, true) : $rawToolResults;

        if (! is_array($decoded)) {
            return $content;
        }

        $allowed = ['ProposeBatch', 'ProposeChapterPlan'];
        $appended = '';

        foreach ($decoded as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if (! in_array($entry['name'] ?? null, $allowed, true)) {
                continue;
            }
            $result = $entry['result'] ?? null;
            if (! is_string($result) || $result === '') {
                continue;
            }
            if (str_contains($content, $result)) {
                continue;
            }
            $appended .= "\n\n".$result;
        }

        return $appended === '' ? $content : rtrim($content).$appended;
    }

    private function ensureAiConfigured(): void
    {
        set_time_limit(300);

        $setting = AiSetting::activeProvider();

        abort_if(
            ! $setting || ! $setting->isConfigured(),
            422,
            __('No AI provider configured.'),
        );

        $setting->injectConfig();
    }
}
