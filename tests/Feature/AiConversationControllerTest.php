<?php

use App\Models\Book;
use App\Models\License;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    License::factory()->create();
    $this->book = Book::factory()->create();
});

function insertConversation(?string $id = null, ?int $userId = null, string $title = 'Test'): string
{
    $id ??= (string) Str::uuid7();

    DB::table('agent_conversations')->insert([
        'id' => $id,
        'user_id' => $userId,
        'title' => $title,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function insertConversationMessage(string $conversationId, string $role, string $content, ?int $userId = null): void
{
    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversationId,
        'user_id' => $userId,
        'agent' => 'TestAgent',
        'role' => $role,
        'content' => $content,
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('messages endpoint returns 404 for unknown conversation id', function () {
    $this->getJson(route('books.ai.conversations.messages', [
        'book' => $this->book->id,
        'conversation' => (string) Str::uuid7(),
    ]))->assertNotFound();
});

test('messages endpoint returns user/assistant messages for existing conversation', function () {
    $conversationId = insertConversation();
    insertConversationMessage($conversationId, 'user', 'hello');
    insertConversationMessage($conversationId, 'assistant', 'hi there');
    // Non-user/assistant roles (tool) must be filtered out.
    insertConversationMessage($conversationId, 'tool', '{"result": "ignored"}');

    $response = $this->getJson(route('books.ai.conversations.messages', [
        'book' => $this->book->id,
        'conversation' => $conversationId,
    ]))->assertOk();

    $response->assertJsonCount(2);
    $response->assertJsonFragment(['role' => 'user', 'content' => 'hello']);
    $response->assertJsonFragment(['role' => 'assistant', 'content' => 'hi there']);
});

test('destroy endpoint returns 404 for unknown conversation id and deletes nothing', function () {
    $realConversationId = insertConversation();
    insertConversationMessage($realConversationId, 'user', 'keep me');

    $this->deleteJson(route('books.ai.conversations.destroy', [
        'book' => $this->book->id,
        'conversation' => (string) Str::uuid7(),
    ]))->assertNotFound();

    expect(DB::table('agent_conversations')->where('id', $realConversationId)->exists())->toBeTrue();
    expect(DB::table('agent_conversation_messages')->where('conversation_id', $realConversationId)->count())->toBe(1);
});

test('destroy endpoint removes conversation and its messages', function () {
    $conversationId = insertConversation();
    insertConversationMessage($conversationId, 'user', 'bye');
    insertConversationMessage($conversationId, 'assistant', 'goodbye');

    $this->deleteJson(route('books.ai.conversations.destroy', [
        'book' => $this->book->id,
        'conversation' => $conversationId,
    ]))->assertNoContent();

    expect(DB::table('agent_conversations')->where('id', $conversationId)->exists())->toBeFalse();
    expect(DB::table('agent_conversation_messages')->where('conversation_id', $conversationId)->count())->toBe(0);
});
