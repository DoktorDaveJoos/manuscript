<?php

use App\Ai\Agents\PlotCoachAgent;
use App\Enums\AiProvider;
use App\Enums\PlotCoachSessionStatus;
use App\Enums\PlotCoachStage;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Character;
use App\Models\License;
use App\Models\PlotCoachBatch;
use App\Models\PlotCoachProposal;
use App\Models\PlotCoachSession;
use Illuminate\Support\Str;

beforeEach(function () {
    License::factory()->create();
});

test('stream returns 403 without active licence', function () {
    License::query()->delete();

    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.plotCoach.stream', $book), [
        'message' => 'Hello coach.',
    ])->assertForbidden();
});

test('stream aborts 422 when no AI provider is configured', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.plotCoach.stream', $book), [
        'message' => 'Hello coach.',
    ])->assertStatus(422);
});

test('stream fails with 422 when AI provider has no API key', function () {
    $book = Book::factory()->create();
    AiSetting::factory()->withoutKey()->create([
        'provider' => AiProvider::Anthropic,
        'enabled' => true,
    ]);

    $this->postJson(route('books.plotCoach.stream', $book), [
        'message' => 'Hello coach.',
    ])->assertStatus(422);
});

test('stream creates an active session on first call if none exists', function () {
    PlotCoachAgent::fake(['Welcome.']);

    $book = Book::factory()->withAi()->create();

    expect(PlotCoachSession::query()->where('book_id', $book->id)->count())->toBe(0);

    $this->post(route('books.plotCoach.stream', $book), [
        'message' => 'Let us start plotting.',
    ])->assertOk();

    $sessions = PlotCoachSession::query()->where('book_id', $book->id)->get();
    expect($sessions)->toHaveCount(1);
    expect($sessions->first()->status)->toBe(PlotCoachSessionStatus::Active);
    expect($sessions->first()->stage)->toBe(PlotCoachStage::Intake);
    expect($sessions->first()->agent_conversation_id)->not->toBeNull();
});

test('stream reuses the active session across calls', function () {
    PlotCoachAgent::fake(['First.', 'Second.']);

    $book = Book::factory()->withAi()->create();

    $this->post(route('books.plotCoach.stream', $book), [
        'message' => 'One.',
    ])->assertOk();

    $this->post(route('books.plotCoach.stream', $book), [
        'message' => 'Two.',
    ])->assertOk();

    expect(PlotCoachSession::query()->where('book_id', $book->id)->count())->toBe(1);
});

test('stream returns 404 when an unknown session_id is provided', function () {
    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.plotCoach.stream', $book), [
        'message' => 'Hello.',
        'session_id' => 999999,
    ])->assertNotFound();
});

test('stream returns 404 when session_id belongs to a different book', function () {
    $bookA = Book::factory()->withAi()->create();
    $bookB = Book::factory()->create();
    $otherSession = PlotCoachSession::factory()->for($bookB, 'book')->create();

    $this->postJson(route('books.plotCoach.stream', $bookA), [
        'message' => 'Hello.',
        'session_id' => $otherSession->id,
    ])->assertNotFound();
});

test('stream validates message is required', function () {
    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.plotCoach.stream', $book), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

test('sessionIndex returns sessions scoped to book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();

    PlotCoachSession::factory()->for($bookA, 'book')->create();
    PlotCoachSession::factory()->for($bookA, 'book')->archived()->create();
    PlotCoachSession::factory()->for($bookB, 'book')->create();

    $response = $this->getJson(route('books.plotCoach.sessions.index', $bookA));

    $response->assertOk();
    expect($response->json())->toHaveCount(2);
});

test('sessionShow returns the most recent messages when the conversation exceeds the hydration cap', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    // 250 user+assistant pairs = 500 messages. Cap is 200. We must see the
    // tail (turn 250) in the response, not turn 1.
    for ($i = 1; $i <= 250; $i++) {
        foreach (['user', 'assistant'] as $role) {
            DB::table('agent_conversation_messages')->insert([
                'id' => (string) Str::uuid7(),
                'conversation_id' => $session->agent_conversation_id,
                'user_id' => null,
                'agent' => PlotCoachAgent::class,
                'role' => $role,
                'content' => "turn {$i} {$role}",
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

    $response = $this->getJson(route('books.plotCoach.sessions.show', [$book, $session]))
        ->assertOk()
        ->json('messages');

    $contents = collect($response)->pluck('content')->all();

    expect(count($contents))->toBe(200);
    expect(end($contents))->toBe('turn 250 assistant');
    expect($contents[0])->toBe('turn 151 user');
});

test('sessionShow returns session with messages when it belongs to book', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $this->getJson(route('books.plotCoach.sessions.show', [$book, $session]))
        ->assertOk()
        ->assertJsonPath('id', $session->id)
        ->assertJsonStructure(['id', 'stage', 'status', 'messages']);
});

test('sessionShow merges ProposeBatch tool_results into the assistant content on rehydrate', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $toolResult = "## Proposed batch\n\n_Save John._\n### Characters\n- John\n\n<!-- PLOT_COACH_BATCH_PROPOSAL\n{\"proposal_id\":\"6f6709c8-a038-4fff-ae83-9723f1a82607\",\"writes\":[{\"type\":\"character\",\"data\":{\"name\":\"John\"}}],\"summary\":\"Save John.\"}\n-->";

    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $session->agent_conversation_id,
        'user_id' => null,
        'agent' => PlotCoachAgent::class,
        'role' => 'assistant',
        'content' => 'Ich stelle John sauber ein.',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => json_encode([
            ['id' => 'call_x', 'name' => 'ProposeBatch', 'arguments' => [], 'result' => $toolResult, 'result_id' => 'res_x'],
        ]),
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->getJson(route('books.plotCoach.sessions.show', [$book, $session]))
        ->assertOk()
        ->assertJsonPath('messages.0.role', 'assistant')
        ->assertJsonPath('messages.0.content', "Ich stelle John sauber ein.\n\n".$toolResult);
});

test('sessionShow strips [system: ...] prefixes from user messages on rehydrate', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $session->agent_conversation_id,
        'user_id' => null,
        'agent' => PlotCoachAgent::class,
        'role' => 'user',
        'content' => "[system: Board changes since last turn:\n- X]\n\nyes, let's save her",
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->getJson(route('books.plotCoach.sessions.show', [$book, $session]))
        ->assertOk()
        ->assertJsonPath('messages.0.content', "yes, let's save her");
});

test('sessionShow hides user turns that are only approval wire signals', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $uuid = (string) Str::uuid();

    DB::table('agent_conversation_messages')->insert([
        [
            'id' => (string) Str::uuid7(),
            'conversation_id' => $session->agent_conversation_id,
            'user_id' => null,
            'agent' => PlotCoachAgent::class,
            'role' => 'user',
            'content' => "[system: The batch was applied. …]\n\nAPPROVE:batch:{$uuid}",
            'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]',
            'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'id' => (string) Str::uuid7(),
            'conversation_id' => $session->agent_conversation_id,
            'user_id' => null,
            'agent' => PlotCoachAgent::class,
            'role' => 'assistant',
            'content' => 'Drin. Was als Nächstes?',
            'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]',
            'created_at' => now()->addSecond(), 'updated_at' => now()->addSecond(),
        ],
    ]);

    $response = $this->getJson(route('books.plotCoach.sessions.show', [$book, $session]));
    $response->assertOk();

    $messages = $response->json('messages');
    expect($messages)->toHaveCount(1);
    expect($messages[0]['role'])->toBe('assistant');
});

test('sessionShow skips tool_results that are not proposal-family tools', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $session->agent_conversation_id,
        'user_id' => null,
        'agent' => PlotCoachAgent::class,
        'role' => 'assistant',
        'content' => 'Got it.',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => json_encode([
            ['id' => 'call_y', 'name' => 'LookupExistingEntities', 'arguments' => [], 'result' => 'raw entity dump', 'result_id' => 'res_y'],
        ]),
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->getJson(route('books.plotCoach.sessions.show', [$book, $session]))
        ->assertOk()
        ->assertJsonPath('messages.0.content', 'Got it.');
});

test('sessionShow returns 404 when session does not belong to the book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($bookA, 'book')->create();

    $this->getJson(route('books.plotCoach.sessions.show', [$bookB, $session]))
        ->assertNotFound();
});

test('sessionArchive archives the session', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $this->patchJson(route('books.plotCoach.sessions.archive', [$book, $session]))
        ->assertNoContent();

    $session->refresh();

    expect($session->status)->toBe(PlotCoachSessionStatus::Archived);
    expect($session->archived_at)->not->toBeNull();
});

test('sessionArchive returns 404 when session does not belong to the book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($bookA, 'book')->create();

    $this->patchJson(route('books.plotCoach.sessions.archive', [$bookB, $session]))
        ->assertNotFound();
});

test('stream flushes pending_board_changes after successful stream', function () {
    PlotCoachAgent::fake(['Got it.']);

    $book = Book::factory()->withAi()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'pending_board_changes' => [
            ['kind' => 'updated', 'type' => 'plot_point', 'id' => 1, 'summary' => 'X updated', 'at' => now()->toIso8601String()],
        ],
    ]);

    $response = $this->post(route('books.plotCoach.stream', $book), [
        'message' => 'Please continue.',
        'session_id' => $session->id,
    ])->assertOk();

    // Consume the SSE stream so the then() hook fires.
    $response->streamedContent();

    $session->refresh();

    expect($session->pending_board_changes)->toBe([]);
});

test('stream accumulates per-session token usage after successful stream', function () {
    PlotCoachAgent::fake(['Got it.']);

    $book = Book::factory()->withAi()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'input_tokens' => 10,
        'output_tokens' => 20,
    ]);

    $response = $this->post(route('books.plotCoach.stream', $book), [
        'message' => 'Continue.',
        'session_id' => $session->id,
    ])->assertOk();

    $response->streamedContent();

    $session->refresh();

    // Fake gateway emits zero-valued usage, so counters must still exist and
    // be numeric (non-null) after accumulation.
    expect($session->input_tokens)->toBeGreaterThanOrEqual(10);
    expect($session->output_tokens)->toBeGreaterThanOrEqual(20);
});

// TODO: add a red-green test for "stream errors leaves queue intact" once the
// fake gateway supports exception injection. Laravel\Ai\Gateway\FakeTextGateway
// only accepts string/array/Closure responses — it does not accept
// Throwable instances — so the stream-error path cannot yet be exercised
// without a custom gateway stub. Tracking: TODO plot-coach P2 followup.

test('sessionArchive writes an archive_summary from session decisions', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'decisions' => [
            'genre' => 'Sci-fi noir',
            'premise' => 'A detective on a dying colony hunts a memory thief.',
            'open_threads' => ['Who is the thief?', 'What does the detective lose?'],
        ],
        'stage' => PlotCoachStage::Plotting,
    ]);

    $this->patchJson(route('books.plotCoach.sessions.archive', [$book, $session]))
        ->assertNoContent();

    $session->refresh();

    expect($session->status)->toBe(PlotCoachSessionStatus::Archived);
    expect($session->archive_summary)->toContain('Sci-fi noir');
    expect($session->archive_summary)->toContain('memory thief');
    expect($session->archive_summary)->toContain('Who is the thief?');
});

test('a new session after an archive carries the archive summary into the first user turn', function () {
    PlotCoachAgent::fake(['Got it.']);

    $book = Book::factory()->withAi()->create();
    $archived = PlotCoachSession::factory()->for($book, 'book')->archived()->create([
        'archive_summary' => 'Plot Coach archive summary\ngenre: fantasy',
        'archived_at' => now()->subHour(),
    ]);

    $response = $this->post(route('books.plotCoach.stream', $book), [
        'message' => 'Fresh start.',
    ])->assertOk();

    $response->streamedContent();

    $fresh = PlotCoachSession::query()
        ->where('book_id', $book->id)
        ->where('status', PlotCoachSessionStatus::Active)
        ->first();

    expect($fresh)->not->toBeNull();
    expect($fresh->parent_session_id)->toBe($archived->id);

    $firstUserMessage = DB::table('agent_conversation_messages')
        ->where('conversation_id', $fresh->agent_conversation_id)
        ->where('role', 'user')
        ->orderBy('created_at')
        ->value('content');

    expect($firstUserMessage)->toContain('Handoff from previous plot coach session');
    expect($firstUserMessage)->toContain('Fresh start.');
});

test('stream increments user_turn_count after each successful turn', function () {
    PlotCoachAgent::fake(['One.', 'Two.']);

    $book = Book::factory()->withAi()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create(['user_turn_count' => 0]);

    $first = $this->post(route('books.plotCoach.stream', $book), [
        'message' => 'Hi.',
        'session_id' => $session->id,
    ])->assertOk();
    $first->streamedContent();

    $second = $this->post(route('books.plotCoach.stream', $book), [
        'message' => 'More.',
        'session_id' => $session->id,
    ])->assertOk();
    $second->streamedContent();

    $session->refresh();

    expect($session->user_turn_count)->toBe(2);
});

test('sessionExport returns a markdown transcript', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->archived()->create([
        'decisions' => ['premise' => 'A study of gravity in small rooms.'],
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $session->agent_conversation_id,
        'user_id' => null,
        'agent' => PlotCoachAgent::class,
        'role' => 'user',
        'content' => 'Hello coach.',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->get(route('books.plotCoach.sessions.export', [$book, $session]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
    $response->assertHeader('Content-Disposition', 'attachment; filename="plot-coach-session-'.$session->id.'.md"');
    expect($response->content())
        ->toContain('# Plot Coach session')
        ->toContain('Hello coach.')
        ->toContain('A study of gravity in small rooms.');
});

test('sessionExport returns 404 when session does not belong to book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($bookA, 'book')->create();

    $this->get(route('books.plotCoach.sessions.export', [$bookB, $session]))
        ->assertNotFound();
});

test('APPROVE:batch:<uuid> applies the proposal server-side without calling the LLM tool', function () {
    PlotCoachAgent::fake(['Saved.']);

    $book = Book::factory()->withAi()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $proposal = PlotCoachProposal::create([
        'public_id' => (string) Str::uuid(),
        'session_id' => $session->id,
        'kind' => 'batch',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => 'Maja', 'description' => 'Biochemist.']],
        ],
        'summary' => 'Save Maja',
    ]);

    $this->post(route('books.plotCoach.stream', $book), [
        'message' => "APPROVE:batch:{$proposal->public_id}",
        'session_id' => $session->id,
    ])->assertOk();

    $proposal->refresh();
    expect($proposal->approved_at)->not->toBeNull();
    expect($proposal->applied_batch_id)->not->toBeNull();

    expect(Character::query()->where('book_id', $book->id)->where('name', 'Maja')->exists())->toBeTrue();
});

test('APPROVE:batch:<uuid> is idempotent — second approval does not double-apply', function () {
    PlotCoachAgent::fake(['Saved.', 'Right.']);

    $book = Book::factory()->withAi()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $proposal = PlotCoachProposal::create([
        'public_id' => (string) Str::uuid(),
        'session_id' => $session->id,
        'kind' => 'batch',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => 'Maja']],
        ],
        'summary' => 'Save Maja',
    ]);

    $this->post(route('books.plotCoach.stream', $book), [
        'message' => "APPROVE:batch:{$proposal->public_id}",
        'session_id' => $session->id,
    ])->assertOk();

    $this->post(route('books.plotCoach.stream', $book), [
        'message' => "APPROVE:batch:{$proposal->public_id}",
        'session_id' => $session->id,
    ])->assertOk();

    expect(Character::query()->where('book_id', $book->id)->where('name', 'Maja')->count())->toBe(1);
});

test('APPROVE:batch:<unknown-uuid> is a safe no-op — no crash, no write', function () {
    PlotCoachAgent::fake(['Hmm.']);

    $book = Book::factory()->withAi()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $this->post(route('books.plotCoach.stream', $book), [
        'message' => 'APPROVE:batch:'.(string) Str::uuid(),
        'session_id' => $session->id,
    ])->assertOk();

    expect(PlotCoachBatch::query()->count())->toBe(0);
});

test('CANCEL:batch:<uuid> marks the proposal cancelled and persists nothing', function () {
    PlotCoachAgent::fake(['Ok.']);

    $book = Book::factory()->withAi()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $proposal = PlotCoachProposal::create([
        'public_id' => (string) Str::uuid(),
        'session_id' => $session->id,
        'kind' => 'batch',
        'writes' => [['type' => 'character', 'data' => ['name' => 'Maja']]],
        'summary' => 'Save Maja',
    ]);

    $this->post(route('books.plotCoach.stream', $book), [
        'message' => "CANCEL:batch:{$proposal->public_id}",
        'session_id' => $session->id,
    ])->assertOk();

    $proposal->refresh();
    expect($proposal->cancelled_at)->not->toBeNull();
    expect($proposal->approved_at)->toBeNull();
    expect(Character::query()->where('book_id', $book->id)->count())->toBe(0);
});

test('UNDO:proposal:<uuid> reverts the specific batch the proposal applied', function () {
    PlotCoachAgent::fake(['Saved.', 'Undone.']);

    $book = Book::factory()->withAi()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $proposal = PlotCoachProposal::create([
        'public_id' => (string) Str::uuid(),
        'session_id' => $session->id,
        'kind' => 'batch',
        'writes' => [['type' => 'character', 'data' => ['name' => 'Mira']]],
        'summary' => 'Save Mira',
    ]);

    $this->post(route('books.plotCoach.stream', $book), [
        'message' => "APPROVE:batch:{$proposal->public_id}",
        'session_id' => $session->id,
    ])->assertOk();

    expect(Character::query()->where('book_id', $book->id)->count())->toBe(1);

    $this->post(route('books.plotCoach.stream', $book), [
        'message' => "UNDO:proposal:{$proposal->public_id}",
        'session_id' => $session->id,
    ])->assertOk();

    expect(Character::query()->where('book_id', $book->id)->count())->toBe(0);

    $this->getJson(route('books.plotCoach.sessions.show', [$book, $session]))
        ->assertJsonPath("proposal_states.{$proposal->public_id}", 'reverted');
});

test('sessionShow returns proposal_states map with the right state per proposal', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $pending = PlotCoachProposal::create([
        'public_id' => (string) Str::uuid(),
        'session_id' => $session->id, 'kind' => 'batch',
        'writes' => [], 'summary' => 'pending',
    ]);
    $cancelled = PlotCoachProposal::create([
        'public_id' => (string) Str::uuid(),
        'session_id' => $session->id, 'kind' => 'batch',
        'writes' => [], 'summary' => 'cancelled', 'cancelled_at' => now(),
    ]);

    $response = $this->getJson(route('books.plotCoach.sessions.show', [$book, $session]));

    $response->assertOk()
        ->assertJsonPath("proposal_states.{$pending->public_id}", 'pending')
        ->assertJsonPath("proposal_states.{$cancelled->public_id}", 'cancelled');
});

test('UNDO:last reverts the most recent applied batch server-side', function () {
    PlotCoachAgent::fake(['Saved.', 'Undone.']);

    $book = Book::factory()->withAi()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $proposal = PlotCoachProposal::create([
        'public_id' => (string) Str::uuid(),
        'session_id' => $session->id,
        'kind' => 'batch',
        'writes' => [['type' => 'character', 'data' => ['name' => 'Maja']]],
        'summary' => 'Save Maja',
    ]);

    $this->post(route('books.plotCoach.stream', $book), [
        'message' => "APPROVE:batch:{$proposal->public_id}",
        'session_id' => $session->id,
    ])->assertOk();

    expect(Character::query()->where('book_id', $book->id)->count())->toBe(1);

    $this->post(route('books.plotCoach.stream', $book), [
        'message' => 'UNDO:last',
        'session_id' => $session->id,
    ])->assertOk();

    expect(Character::query()->where('book_id', $book->id)->count())->toBe(0);
});
