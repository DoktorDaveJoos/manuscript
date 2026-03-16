<?php

use App\Enums\Genre;
use App\Models\Book;

test('store book with genre and secondary genres persists values', function () {
    $response = $this->post(route('books.store'), [
        'title' => 'Genre Test Book',
        'author' => 'Author',
        'language' => 'en',
        'genre' => 'thriller',
        'secondary_genres' => ['mystery', 'crime'],
    ]);

    $response->assertRedirect();

    $book = Book::query()->where('title', 'Genre Test Book')->firstOrFail();

    expect($book->genre)->toBe(Genre::Thriller)
        ->and($book->secondary_genres)->toBe(['mystery', 'crime']);
});

test('store book without genre leaves both fields null', function () {
    $response = $this->post(route('books.store'), [
        'title' => 'No Genre Book',
        'author' => 'Author',
        'language' => 'en',
    ]);

    $response->assertRedirect();

    $book = Book::query()->where('title', 'No Genre Book')->firstOrFail();

    expect($book->genre)->toBeNull()
        ->and($book->secondary_genres)->toBeNull();
});

test('validation rejects invalid genre value', function () {
    $response = $this->post(route('books.store'), [
        'title' => 'Bad Genre',
        'author' => 'Author',
        'language' => 'en',
        'genre' => 'not_a_real_genre',
    ]);

    $response->assertSessionHasErrors('genre');
});

test('genreSnippet returns empty string when no genre set', function () {
    $book = Book::factory()->create();

    expect($book->genreSnippet())->toBe('');
});

test('genreSnippet returns correct string with primary genre only', function () {
    $book = Book::factory()->withGenre(Genre::ScienceFiction)->create();

    expect($book->genreSnippet())->toBe('Genre context: This is a Science Fiction manuscript.');
});

test('genreSnippet returns correct string with primary and secondary genres', function () {
    $book = Book::factory()->withGenre(Genre::Thriller, [Genre::Mystery, Genre::Crime])->create();

    expect($book->genreSnippet())->toBe('Genre context: This is a Thriller manuscript. It also draws from: Mystery, Crime.');
});
