<?php

namespace App\Http\Controllers;

use App\Ai\Agents\PlotCoachAgent;
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
use Laravel\Ai\Contracts\ConversationStore;
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

            return $this->streamWithConversationId(
                $agent->stream($request->input('message')),
                $session->agent_conversation_id,
            );
        });
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

            if ($session) {
                return $session;
            }
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
