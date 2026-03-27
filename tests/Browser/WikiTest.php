<?php

use App\Models\Book;
use App\Models\Character;
use App\Models\Storyline;

it('renders wiki page with empty state', function () {
    $book = Book::factory()->create(['title' => 'Wiki Test Book']);
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $page = visit("/books/{$book->id}/wiki");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Wiki')
        ->assertSee('Characters')
        ->assertSee('No characters yet');
});

it('shows all wiki tabs', function () {
    $book = Book::factory()->create(['title' => 'Tabs Test Book']);
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $page = visit("/books/{$book->id}/wiki");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Characters')
        ->assertSee('Locations')
        ->assertSee('Organizations')
        ->assertSee('Items')
        ->assertSee('Lore');
});

it('creates a character from wiki page', function () {
    $book = Book::factory()->create(['title' => 'Character Create Book']);
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $page = visit("/books/{$book->id}/wiki");

    $page->assertNoJavaScriptErrors()
        ->click('button.rounded-md.border.border-border')
        ->assertSee('Character')
        ->click('Character')
        ->assertSee('New Character')
        ->assertNoJavaScriptErrors()
        ->type('input[placeholder="Enter character name..."]', 'Alice Wonderland')
        ->click('Create Character')
        ->assertNoJavaScriptErrors()
        ->assertSee('Alice Wonderland');

    expect(Character::where('name', 'Alice Wonderland')->exists())->toBeTrue();
});

it('displays existing characters on wiki page', function () {
    $book = Book::factory()->create(['title' => 'Existing Characters Book']);
    Storyline::factory()->for($book)->create(['name' => 'Main']);
    Character::factory()->create(['book_id' => $book->id, 'name' => 'Bob Builder']);
    Character::factory()->create(['book_id' => $book->id, 'name' => 'Jane Doe']);

    $page = visit("/books/{$book->id}/wiki");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Bob Builder')
        ->assertSee('Jane Doe');
});
