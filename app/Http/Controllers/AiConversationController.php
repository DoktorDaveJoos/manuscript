<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AiConversationController extends Controller
{
    public function messages(Book $book, string $conversation): JsonResponse
    {
        $this->assertConversationExists($conversation);

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

    public function destroy(Book $book, string $conversation): Response
    {
        $this->assertConversationExists($conversation);

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

    private function assertConversationExists(string $conversation): void
    {
        abort_unless(
            DB::table('agent_conversations')->where('id', $conversation)->exists(),
            404,
        );
    }
}
