<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;

it('loads editor page with single pane from query', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();

    $response = $this->get("/books/{$book->id}/editor?panes={$chapter->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chapters/editor')
            ->has('book')
            ->where('initialPanes', (string) $chapter->id)
        );
});

it('loads editor page with multiple panes from query', function () {
    $book = Book::factory()->create();
    $ch1 = Chapter::factory()->for($book)->create();
    $ch2 = Chapter::factory()->for($book)->create();

    $response = $this->get("/books/{$book->id}/editor?panes={$ch1->id},{$ch2->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chapters/editor')
            ->where('initialPanes', "{$ch1->id},{$ch2->id}")
        );
});

it('redirects chapters.show to editor with pane', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();

    $response = $this->get("/books/{$book->id}/chapters/{$chapter->id}");

    $response->assertRedirect("/books/{$book->id}/editor?panes={$chapter->id}");
});

it('falls back to first chapter when no panes query', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->for($book)->create();

    $response = $this->get("/books/{$book->id}/editor");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chapters/editor')
            ->where('fallbackChapterId', $chapter->id)
        );
});

it('shows empty state when book has no chapters', function () {
    $book = Book::factory()->create();

    $response = $this->get("/books/{$book->id}/editor");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('chapters/empty'));
});

it('saves notes independently for two chapters in splitscreen', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapterA = Chapter::factory()->for($book)->for($storyline)->create(['notes' => null]);
    $chapterB = Chapter::factory()->for($book)->for($storyline)->create(['notes' => null]);

    $this->patchJson(route('chapters.updateNotes', [$book, $chapterA]), [
        'notes' => 'Notes for A',
    ])->assertOk();

    $this->patchJson(route('chapters.updateNotes', [$book, $chapterB]), [
        'notes' => 'Notes for B',
    ])->assertOk();

    expect($chapterA->fresh()->notes)->toBe('Notes for A');
    expect($chapterB->fresh()->notes)->toBe('Notes for B');
});
