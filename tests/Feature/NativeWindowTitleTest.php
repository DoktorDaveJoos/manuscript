<?php

use App\Models\Book;
use Native\Desktop\Facades\Window;
use Native\Desktop\Windows\Window as NativeWindow;

beforeEach(function () {
    config([
        'app.name' => 'Manuscript',
        'nativephp-internal.running' => true,
    ]);
    Window::fake();
});

test('book pages show the active book in the native window title', function () {
    $book = Book::factory()->create(['title' => 'The Glass Orchard']);
    $nativeWindow = Mockery::mock(NativeWindow::class)->makePartial();

    Window::alwaysReturnWindows([$nativeWindow]);

    $nativeWindow->shouldReceive('title')
        ->once()
        ->with('The Glass Orchard — Manuscript')
        ->andReturnSelf();

    $this->get(route('books.dashboard', $book))
        ->assertSuccessful();
});

test('pages outside a book reset the native window title', function () {
    $nativeWindow = Mockery::mock(NativeWindow::class)->makePartial();

    Window::alwaysReturnWindows([$nativeWindow]);

    $nativeWindow->shouldReceive('title')
        ->once()
        ->with('Manuscript')
        ->andReturnSelf();

    $this->get(route('books.index'))
        ->assertSuccessful();
});

test('web requests do not use the native window API outside the desktop runtime', function () {
    config(['nativephp-internal.running' => false]);

    $this->get(route('books.index'))
        ->assertSuccessful();
});

test('background JSON requests do not overwrite the native window title', function () {
    $this->get(route('ready'))
        ->assertSuccessful()
        ->assertJson(['ready' => true]);
});
