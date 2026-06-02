<?php

use App\Ai\Agents\EditorialChatAgent;
use App\Ai\Contracts\BelongsToBook;
use App\Models\Book;
use App\Models\License;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\AssistantMessage;

beforeEach(function () {
    License::factory()->create();
});

test('EditorialChatAgent implements required contracts', function () {
    $book = Book::factory()->create();
    $agent = new EditorialChatAgent($book, 'Some editorial context');

    expect($agent)
        ->toBeInstanceOf(Agent::class)
        ->toBeInstanceOf(BelongsToBook::class)
        ->toBeInstanceOf(Conversational::class)
        ->toBeInstanceOf(HasMiddleware::class)
        ->toBeInstanceOf(HasTools::class);
});

test('EditorialChatAgent returns the book instance', function () {
    $book = Book::factory()->create();
    $agent = new EditorialChatAgent($book, 'Some editorial context');

    expect($agent->book())->toBe($book);
});

test('EditorialChatAgent instructions include editorial context and book details', function () {
    $book = Book::factory()->create([
        'title' => 'Test Novel',
        'author' => 'Jane Author',
        'language' => 'English',
    ]);

    $editorialContext = "Executive Summary: The manuscript shows promise.\nSection: Plot\nFinding: Plot hole in chapter 3.";
    $agent = new EditorialChatAgent($book, $editorialContext);

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Test Novel')
        ->toContain('Jane Author')
        ->toContain('English')
        ->toContain('Executive Summary: The manuscript shows promise.')
        ->toContain('Plot hole in chapter 3.');
});

test('EditorialChatAgent loads messages from conversation store', function () {
    $book = Book::factory()->create();

    $conversationId = resolve(ConversationStore::class)
        ->storeConversation(null, 'Test editorial conversation');

    DB::table('agent_conversation_messages')->insert([
        ['id' => Str::uuid7()->toString(), 'conversation_id' => $conversationId, 'user_id' => null, 'agent' => EditorialChatAgent::class, 'role' => 'user', 'content' => 'Tell me more about this finding.', 'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]', 'created_at' => now(), 'updated_at' => now()],
        ['id' => Str::uuid7()->toString(), 'conversation_id' => $conversationId, 'user_id' => null, 'agent' => EditorialChatAgent::class, 'role' => 'assistant', 'content' => 'The finding relates to...', 'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]', 'created_at' => now(), 'updated_at' => now()],
        ['id' => Str::uuid7()->toString(), 'conversation_id' => $conversationId, 'user_id' => null, 'agent' => EditorialChatAgent::class, 'role' => 'user', 'content' => 'How can I fix it?', 'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $agent = new EditorialChatAgent($book, 'context');
    $agent->continue($conversationId, (object) ['id' => 1]);
    $messages = iterator_to_array($agent->messages());

    expect($messages)->toHaveCount(3)
        ->and($messages[0]->content)->toBe('Tell me more about this finding.')
        ->and($messages[1])->toBeInstanceOf(AssistantMessage::class)
        ->and($messages[1]->content)->toBe('The finding relates to...')
        ->and($messages[2]->content)->toBe('How can I fix it?');
});

test('EditorialChatAgent returns empty messages when no history provided', function () {
    $book = Book::factory()->create();
    $agent = new EditorialChatAgent($book, 'context');

    $messages = iterator_to_array($agent->messages());

    expect($messages)->toBeEmpty();
});

test('EditorialChatAgent provides tools', function () {
    $book = Book::factory()->create();
    $agent = new EditorialChatAgent($book, 'context');

    $tools = iterator_to_array($agent->tools());

    expect($tools)->toHaveCount(2);
});

test('EditorialChatAgent provides middleware', function () {
    $book = Book::factory()->create();
    $agent = new EditorialChatAgent($book, 'context');

    expect($agent->middleware())->toHaveCount(1);
});
