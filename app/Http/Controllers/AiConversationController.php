<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AiConversationController extends Controller
{
    public function messages(Request $request, Book $book, string $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversation)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->limit(200)
            ->get(['role', 'content'])
            ->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ]);

        return response()->json($messages);
    }

    public function destroy(Request $request, Book $book, string $conversation): Response
    {
        $this->authorizeConversation($request, $conversation);

        DB::transaction(function () use ($conversation) {
            DB::table('agent_conversation_messages')
                ->where('conversation_id', $conversation)
                ->delete();

            DB::table('agent_conversations')
                ->where('id', $conversation)
                ->delete();
        });

        return response()->noContent();
    }

    private function authorizeConversation(Request $request, string $conversation): void
    {
        $query = DB::table('agent_conversations')->where('id', $conversation);

        if ($userId = $request->user()?->id) {
            $query->where('user_id', $userId);
        }

        abort_unless($query->exists(), 404);
    }
}
