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

test('transcript export and digests exclude [system: ...] scaffolding and wire signals', function () {
    $book = Book::factory()->create();

    $conversationId = (string) Str::uuid();
    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => null,
        'title' => 'scaffolded',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'agent_conversation_id' => $conversationId,
    ]);

    $uuid = (string) Str::uuid();

    $rows = [
        ['role' => 'user', 'content' => 'Save Maja for me.'],
        ['role' => 'assistant', 'content' => 'Let me save Maja.'],
        ['role' => 'user', 'content' => "[system: The batch was applied. Reply with one short line.]\n\nAPPROVE:batch:{$uuid}"],
        ['role' => 'assistant', 'content' => 'Drin. Was als Nächstes?'],
    ];

    foreach ($rows as $i => $row) {
        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversationId,
            'agent' => PlotCoachAgent::class,
            'role' => $row['role'],
            'content' => $row['content'],
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => now()->addSeconds($i),
            'updated_at' => now(),
        ]);
    }

    $markdown = (new PlotCoachSessionSummarizer)->buildTranscriptMarkdown($session);

    expect($markdown)
        ->toContain('Save Maja for me.')
        ->toContain('Drin. Was als Nächstes?')
        ->not->toContain('[system:')
        ->not->toContain('APPROVE:batch:');
});

test('buildInSessionDigest overlaps the verbatim replay window so refresh lag leaves no gap', function () {
    $book = Book::factory()->create();

    $conversationId = (string) Str::uuid();
    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => null,
        'title' => 'overlap',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'agent_conversation_id' => $conversationId,
    ]);

    // 60 turns = 120 messages. The digest must cover everything except the
    // last (TAIL - OVERLAP) = 10 messages: by the time the next refresh runs
    // (5 user turns = 10 messages later), the replay window has slid exactly
    // onto the digest's edge.
    seedSummarizerMessages($conversationId, 60);

    $digest = (new PlotCoachSessionSummarizer)->buildInSessionDigest($session);

    expect($digest)
        ->toContain('turn 53 user')
        ->not->toContain('turn 56 user');
});

test('buildInSessionDigest enforces a hard character budget by eliding the oldest messages', function () {
    $book = Book::factory()->create();

    $conversationId = (string) Str::uuid();
    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => null,
        'title' => 'very long',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'agent_conversation_id' => $conversationId,
    ]);

    // 200 turns = 400 messages — naively rendered this would be ~11k chars.
    seedSummarizerMessages($conversationId, 200);

    $digest = (new PlotCoachSessionSummarizer)->buildInSessionDigest($session);

    expect(mb_strlen($digest))->toBeLessThanOrEqual(4400);
    expect($digest)
        ->toContain('elided')
        ->toContain('turn 190 user')
        ->not->toContain('turn 2 user');
});
