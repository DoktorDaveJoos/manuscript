<?php

namespace App\Http\Controllers;

use App\Ai\Agents\PlotCoachAgent;
use App\Enums\CoachingMode;
use App\Enums\PlotCoachSessionStatus;
use App\Enums\PlotCoachStage;
use App\Http\Controllers\Concerns\StreamsConversation;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\PlotCoachSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

            $message = $this->prependBoardChangesNote(
                $session,
                (string) $request->input('message'),
            );

            $stream = $agent->stream($message);

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

    public function sessionMode(Request $request, Book $book, PlotCoachSession $session): JsonResponse
    {
        abort_unless($session->book_id === $book->id, 404);

        $validated = $request->validate([
            'mode' => ['required', Rule::enum(CoachingMode::class)],
        ]);

        $newMode = CoachingMode::from($validated['mode']);
        $oldMode = $session->coaching_mode instanceof CoachingMode
            ? $session->coaching_mode->value
            : null;

        $decisions = $session->decisions ?? [];
        $decisions['mode_changes'] ??= [];
        $decisions['mode_changes'][] = [
            'from' => $oldMode,
            'to' => $newMode->value,
            'at' => now()->toIso8601String(),
        ];

        $session->update([
            'coaching_mode' => $newMode,
            'decisions' => $decisions,
        ]);

        return response()->json([
            'coaching_mode' => $session->coaching_mode,
        ]);
    }

    public function sessionIndex(Book $book): JsonResponse
    {
        $sessions = PlotCoachSession::query()
            ->where('book_id', $book->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'status', 'stage', 'archived_at', 'created_at', 'updated_at']);

        return response()->json($sessions);
    }

    public function sessionShow(Book $book, PlotCoachSession $session): JsonResponse
    {
        abort_unless($session->book_id === $book->id, 404);

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $session->agent_conversation_id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->limit(200)
            ->get(['role', 'content'])
            ->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ]);

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
            'created_at' => $session->created_at,
            'updated_at' => $session->updated_at,
            'messages' => $messages,
        ]);
    }

    public function sessionArchive(Book $book, PlotCoachSession $session): Response
    {
        abort_unless($session->book_id === $book->id, 404);

        $session->update([
            'status' => PlotCoachSessionStatus::Archived,
            'archived_at' => now(),
        ]);

        return response()->noContent();
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

        return PlotCoachSession::query()->create([
            'book_id' => $book->id,
            'agent_conversation_id' => $conversationId,
            'status' => PlotCoachSessionStatus::Active,
            'stage' => PlotCoachStage::Intake,
            'coaching_mode' => null,
            'decisions' => [],
            'pending_board_changes' => [],
        ]);
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
                // cost_cents intentionally left null: the SDK surfaces raw
                // token counts, and cost conversion lives in AiUsageService
                // for per-book attribution only. Per-session cost_cents is
                // deferred until we surface it in the archive UI.
            }

            $fresh->update(['pending_board_changes' => []]);
        });
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
