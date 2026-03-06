<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Storyline;
use App\Models\WritingSession;

test('update sets daily writing goal on book', function () {
    $book = Book::factory()->create(['daily_word_count_goal' => null]);

    $this->putJson(route('books.writing-goal.update', $book), [
        'daily_word_count_goal' => 1000,
    ])
        ->assertOk()
        ->assertJsonFragment(['daily_word_count_goal' => 1000]);

    expect($book->fresh()->daily_word_count_goal)->toBe(1000);
});

test('update validates minimum goal', function () {
    $book = Book::factory()->create();

    $this->putJson(route('books.writing-goal.update', $book), [
        'daily_word_count_goal' => 10,
    ])->assertUnprocessable();
});

test('update validates maximum goal', function () {
    $book = Book::factory()->create();

    $this->putJson(route('books.writing-goal.update', $book), [
        'daily_word_count_goal' => 100000,
    ])->assertUnprocessable();
});

test('content update increments writing session words', function () {
    $book = Book::factory()->create(['daily_word_count_goal' => 500]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 5]);
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true, 'content' => '<p>hello world foo bar baz</p>']);

    $this->putJson(route('chapters.updateContent', [$book, $chapter]), [
        'content' => '<p>hello world foo bar baz qux quux corge grault</p>',
    ])->assertOk();

    $session = WritingSession::where('book_id', $book->id)
        ->whereDate('date', now()->toDateString())
        ->first();

    expect($session)->not->toBeNull();
    expect($session->words_written)->toBeGreaterThan(0);
});

test('content update does not create session when words decrease', function () {
    $book = Book::factory()->create(['daily_word_count_goal' => 500]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 10]);
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true, 'content' => '<p>hello world foo bar baz qux quux corge grault garply</p>']);

    $this->putJson(route('chapters.updateContent', [$book, $chapter]), [
        'content' => '<p>hello world</p>',
    ])->assertOk();

    $session = WritingSession::where('book_id', $book->id)
        ->whereDate('date', now()->toDateString())
        ->first();

    expect($session)->toBeNull();
});

test('content update marks goal_met when threshold reached', function () {
    $book = Book::factory()->create(['daily_word_count_goal' => 5]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 0]);
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true, 'content' => '']);

    $this->putJson(route('chapters.updateContent', [$book, $chapter]), [
        'content' => '<p>one two three four five six seven</p>',
    ])->assertOk();

    $session = WritingSession::where('book_id', $book->id)
        ->whereDate('date', now()->toDateString())
        ->first();

    expect($session)->not->toBeNull();
    expect($session->goal_met)->toBeTrue();
});

test('streak counts consecutive days with goal met', function () {
    $book = Book::factory()->create(['daily_word_count_goal' => 100]);

    // Create sessions for 3 consecutive days before today
    WritingSession::factory()->for($book)->goalMet()->create([
        'date' => now()->subDays(1)->toDateString(),
    ]);
    WritingSession::factory()->for($book)->goalMet()->create([
        'date' => now()->subDays(2)->toDateString(),
    ]);
    WritingSession::factory()->for($book)->goalMet()->create([
        'date' => now()->subDays(3)->toDateString(),
    ]);

    // Gap at day 4 (no session)

    // Today's session with goal met
    WritingSession::factory()->for($book)->goalMet()->create([
        'date' => now()->toDateString(),
    ]);

    $this->get(route('books.dashboard', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('writing_goal.streak', 4)
        );
});

test('streak resets on gap day', function () {
    $book = Book::factory()->create(['daily_word_count_goal' => 100]);

    // Day -1: goal met
    WritingSession::factory()->for($book)->goalMet()->create([
        'date' => now()->subDays(1)->toDateString(),
    ]);

    // Day -2: goal NOT met (gap)
    WritingSession::factory()->for($book)->create([
        'date' => now()->subDays(2)->toDateString(),
        'goal_met' => false,
    ]);

    // Day -3: goal met (doesn't count because of gap at day -2)
    WritingSession::factory()->for($book)->goalMet()->create([
        'date' => now()->subDays(3)->toDateString(),
    ]);

    // Today: goal met
    WritingSession::factory()->for($book)->goalMet()->create([
        'date' => now()->toDateString(),
    ]);

    $this->get(route('books.dashboard', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('writing_goal.streak', 2)
        );
});

test('dashboard includes writing goal data', function () {
    $book = Book::factory()->create(['daily_word_count_goal' => 750]);

    WritingSession::factory()->for($book)->create([
        'date' => now()->toDateString(),
        'words_written' => 300,
        'goal_met' => false,
    ]);

    $this->get(route('books.dashboard', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('writing_goal.daily_word_count_goal', 750)
            ->where('writing_goal.today_words', 300)
            ->where('writing_goal.goal_met_today', false)
            ->where('writing_goal.streak', 0)
        );
});
