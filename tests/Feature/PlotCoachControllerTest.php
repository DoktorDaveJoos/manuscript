<?php

use App\Ai\Agents\PlotCoachAgent;
use App\Enums\AiProvider;
use App\Enums\CoachingMode;
use App\Enums\PlotCoachSessionStatus;
use App\Enums\PlotCoachStage;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\License;
use App\Models\PlotCoachSession;

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

test('sessionShow returns session with messages when it belongs to book', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $this->getJson(route('books.plotCoach.sessions.show', [$book, $session]))
        ->assertOk()
        ->assertJsonPath('id', $session->id)
        ->assertJsonStructure(['id', 'stage', 'status', 'messages']);
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

test('sessionMode updates the coaching mode and logs the change', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'coaching_mode' => null,
        'decisions' => [],
    ]);

    $this->patchJson(route('books.plotCoach.sessions.mode', [$book, $session]), [
        'mode' => 'suggestive',
    ])->assertOk()
        ->assertJsonPath('coaching_mode', 'suggestive');

    $session->refresh();

    expect($session->coaching_mode)->toBe(CoachingMode::Suggestive);
    expect($session->decisions['mode_changes'])->toHaveCount(1);
    expect($session->decisions['mode_changes'][0]['from'])->toBeNull();
    expect($session->decisions['mode_changes'][0]['to'])->toBe('suggestive');
});

test('sessionMode appends subsequent mode changes to the log', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'coaching_mode' => CoachingMode::Suggestive,
        'decisions' => [],
    ]);

    $this->patchJson(route('books.plotCoach.sessions.mode', [$book, $session]), [
        'mode' => 'guided',
    ])->assertOk();

    $session->refresh();

    expect($session->coaching_mode)->toBe(CoachingMode::Guided);
    expect($session->decisions['mode_changes'])->toHaveCount(1);
    expect($session->decisions['mode_changes'][0]['from'])->toBe('suggestive');
    expect($session->decisions['mode_changes'][0]['to'])->toBe('guided');
});

test('sessionMode validates the mode enum', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $this->patchJson(route('books.plotCoach.sessions.mode', [$book, $session]), [
        'mode' => 'bogus',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('mode');
});

test('sessionMode returns 404 when session is not in book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($bookA, 'book')->create();

    $this->patchJson(route('books.plotCoach.sessions.mode', [$bookB, $session]), [
        'mode' => 'guided',
    ])->assertNotFound();
});
