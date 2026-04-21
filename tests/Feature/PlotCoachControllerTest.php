<?php

use App\Ai\Agents\PlotCoachAgent;
use App\Enums\AiProvider;
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
