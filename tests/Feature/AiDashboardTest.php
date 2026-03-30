<?php

use App\Models\AiPreparation;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Models\Storyline;

beforeEach(function () {
    License::factory()->create();
    $this->withoutVite();
});

test('ai dashboard loads without license', function () {
    License::query()->delete();

    $book = Book::factory()->create();

    $this->getJson(route('books.ai.dashboard', $book))
        ->assertOk();
});

test('ai dashboard loads with empty state when no preparation exists', function () {
    $book = Book::factory()->create();

    $this->get(route('books.ai.dashboard', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('books/ai-dashboard')
            ->where('is_prepared', false)
            ->where('ai_preparation', null)
            ->where('health_metrics', null)
            ->where('analyzed_chapters', null)
            ->has('ai_usage')
        );
});

test('ai dashboard loads with empty state when preparation is not completed', function () {
    $book = Book::factory()->create();

    AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'running',
    ]);

    $this->get(route('books.ai.dashboard', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('books/ai-dashboard')
            ->where('is_prepared', false)
        );
});

test('ai dashboard loads with populated state when preparation is completed', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);

    Chapter::factory()->create([
        'book_id' => $book->id,
        'storyline_id' => $storyline->id,
        'reader_order' => 1,
        'hook_score' => 8,
        'entry_hook_score' => 7,
        'exit_hook_score' => 9,
        'tension_score' => 6,
        'micro_tension_score' => 5,
        'scene_purpose' => 'turning_point',
        'value_shift' => 'positive',
        'pacing_feel' => 'brisk',
        'emotional_shift_magnitude' => 5,
        'sensory_grounding' => 3,
        'information_delivery' => 'organic',
    ]);

    AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);

    $this->get(route('books.ai.dashboard', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('books/ai-dashboard')
            ->where('is_prepared', true)
            ->has('health_metrics')
            ->has('health_metrics.composite_score')
            ->has('health_metrics.metrics', 6)
            ->has('analyzed_chapters')
            ->has('analyzed_chapters.data', 1)
            ->has('ai_usage')
        );
});

test('ai dashboard paginates analyzed chapters', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);

    for ($i = 1; $i <= 8; $i++) {
        Chapter::factory()->create([
            'book_id' => $book->id,
            'storyline_id' => $storyline->id,
            'reader_order' => $i,
            'hook_score' => 7,
        ]);
    }

    AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);

    $this->get(route('books.ai.dashboard', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('analyzed_chapters.current_page', 1)
            ->where('analyzed_chapters.last_page', 2)
            ->where('analyzed_chapters.total', 8)
            ->has('analyzed_chapters.data', 5)
        );

    $this->get(route('books.ai.dashboard', ['book' => $book, 'page' => 2]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('analyzed_chapters.current_page', 2)
            ->has('analyzed_chapters.data', 3)
        );
});

test('ai dashboard returns null health metrics when no chapters are analyzed', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);

    Chapter::factory()->create([
        'book_id' => $book->id,
        'storyline_id' => $storyline->id,
        'reader_order' => 1,
        'hook_score' => null,
    ]);

    AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'completed',
    ]);

    $this->get(route('books.ai.dashboard', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('is_prepared', true)
            ->where('health_metrics', null)
        );
});
