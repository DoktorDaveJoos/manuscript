<?php

use App\Models\Book;
use App\Models\Character;
use App\Models\License;
use App\Models\Storyline;
use App\Models\WikiEntry;
use App\Services\FreeTierLimits;

test('free user receives free_tier prop with book counts', function () {
    Book::factory()->create();

    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('free_tier')
            ->where('free_tier.books.count', 1)
            ->where('free_tier.books.limit', FreeTierLimits::MAX_BOOKS)
            ->where('free_tier.export_free_formats', FreeTierLimits::FREE_EXPORT_FORMATS)
        );
});

test('free user receives free_tier prop with book-scoped counts', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create();
    Character::factory()->count(2)->for($book)->create();
    WikiEntry::factory()->for($book)->create();

    $this->get(route('books.dashboard', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('free_tier')
            ->where('free_tier.storylines.count', 1)
            ->where('free_tier.storylines.limit', FreeTierLimits::MAX_STORYLINES)
            ->where('free_tier.wiki_entries.count', 3)
            ->where('free_tier.wiki_entries.limit', FreeTierLimits::MAX_WIKI_ENTRIES)
        );
});

test('pro user receives null free_tier prop', function () {
    License::factory()->create();

    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('free_tier', null)
        );
});

test('free_tier prop has null storylines on non-book pages', function () {
    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('free_tier')
            ->where('free_tier.storylines', null)
            ->where('free_tier.wiki_entries', null)
        );
});
