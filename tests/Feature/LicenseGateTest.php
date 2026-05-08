<?php

use App\Models\Book;
use App\Models\License;

beforeEach(function () {
    // The Pest beforeEach hook seeds a license; clear it so the gate kicks in.
    License::query()->delete();
    License::clearActiveCache();
});

test('unlicensed root redirects to license welcome', function () {
    $this->get(route('books.index'))
        ->assertRedirect(route('license.welcome'));
});

test('unlicensed book route redirects to license welcome', function () {
    $book = Book::factory()->create();

    $this->get(route('books.dashboard', $book))
        ->assertRedirect(route('license.welcome'));
});

test('unlicensed settings redirects to license welcome', function () {
    $this->get(route('settings.index'))
        ->assertRedirect(route('license.welcome'));
});

test('unlicensed json request returns 403', function () {
    $this->postJson(route('books.store'), ['title' => 'New Book', 'author' => 'Me'])
        ->assertStatus(403)
        ->assertJsonPath('message', 'This app requires an active Manuscript license.');
});

test('license welcome page renders without a license', function () {
    $this->get(route('license.welcome'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('license/welcome')
            ->where('license.active', false));
});

test('license activate route is reachable without a license', function () {
    $this->postJson(route('license.activate'), ['license_key' => ''])
        ->assertUnprocessable();
});

test('loading view is reachable without a license', function () {
    $this->get('/loading')->assertOk();
});

test('ready check is reachable without a license', function () {
    $this->get('/ready')->assertOk();
});

test('licensed root reaches the books index', function () {
    License::factory()->create();

    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('books/index'));
});
