<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Models\Storyline;
use Carbon\Carbon;

beforeEach(fn () => License::factory()->create());

test('dashboard returns null nanowrimo when nanowrimo_year is not set', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.dashboard', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('nanowrimo', null)
        );
});

test('dashboard returns active nanowrimo data in November', function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 15));

    $book = Book::factory()->create(['nanowrimo_year' => 2026]);
    $storyline = Storyline::factory()->for($book)->create();
    Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 10000]);
    Chapter::factory()->for($book)->for($storyline)->create(['word_count' => 5000]);

    $this->get(route('books.dashboard', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('nanowrimo')
            ->where('nanowrimo.year', 2026)
            ->where('nanowrimo.is_active', true)
            ->where('nanowrimo.target', 50000)
            ->where('nanowrimo.total_words', 15000)
            ->where('nanowrimo.progress_percent', 30)
            ->where('nanowrimo.days_elapsed', 15)
            ->where('nanowrimo.days_remaining', 15)
        );

    Carbon::setTestNow();
});

test('dashboard returns inactive nanowrimo outside November', function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 15));

    $book = Book::factory()->create(['nanowrimo_year' => 2026]);
    Storyline::factory()->for($book)->create();

    $this->get(route('books.dashboard', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('nanowrimo')
            ->where('nanowrimo.is_active', false)
            ->where('nanowrimo.days_elapsed', 0)
            ->where('nanowrimo.days_remaining', 0)
        );

    Carbon::setTestNow();
});

test('dashboard exposes streak at top level', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();

    $this->get(route('books.dashboard', $book))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('streak')
        );
});
