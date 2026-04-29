<?php

use App\Ai\Agents\PlotCoachAgent;
use App\Models\Book;
use App\Models\PlotCoachSession;
use App\Services\PlotCoachSessionSummarizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function seedSummarizerMessages(string $conversationId, int $turnCount): void
{
    for ($i = 1; $i <= $turnCount; $i++) {
        foreach (['user', 'assistant'] as $role) {
            DB::table('agent_conversation_messages')->insert([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversationId,
                'agent' => PlotCoachAgent::class,
                'role' => $role,
                'content' => $role === 'user' && $i === 1
                    ? 'Opening premise: Maja hides a lab accident in Zurich.'
                    : "turn {$i} {$role}",
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '[]',
                'meta' => '[]',
                'created_at' => now()->addSeconds($i * 2 + ($role === 'assistant' ? 1 : 0)),
                'updated_at' => now(),
            ]);
        }
    }
}

test('buildInSessionDigest returns empty when conversation fits in the replay window', function () {
    $book = Book::factory()->create();

    $conversationId = (string) Str::uuid();
    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => null,
        'title' => 'short',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'agent_conversation_id' => $conversationId,
    ]);

    seedSummarizerMessages($conversationId, 10);

    $digest = (new PlotCoachSessionSummarizer)->buildInSessionDigest($session);

    expect($digest)->toBe('');
});

test('buildInSessionDigest digests everything older than the replay window and preserves the opening', function () {
    $book = Book::factory()->create();

    $conversationId = (string) Str::uuid();
    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => null,
        'title' => 'long',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'agent_conversation_id' => $conversationId,
    ]);

    // 60 turns = 120 messages. 80 pre-tail messages become the digest.
    seedSummarizerMessages($conversationId, 60);

    $digest = (new PlotCoachSessionSummarizer)->buildInSessionDigest($session);

    expect($digest)
        ->toContain('Author:')
        ->toContain('Coach:')
        ->toContain('Opening premise: Maja');

    // Tail messages (turn 60) must NOT appear — they're replayed verbatim.
    expect($digest)->not->toContain('turn 60 user');
});
