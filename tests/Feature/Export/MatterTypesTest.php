<?php

use App\Models\Book;
use App\Models\Chapter;

it('stores per-book publish fields', function () {
    $book = Book::factory()->create([
        'copyright_text' => '© 2026 Test Author',
        'dedication_text' => 'For my family',
        'epigraph_text' => '"All we have to decide..."',
        'epigraph_attribution' => '— J.R.R. Tolkien',
        'acknowledgment_text' => 'Thanks to everyone',
        'about_author_text' => 'Jane writes thrillers',
        'also_by_text' => "Book One\nBook Two",
        'publisher_name' => 'Self Published',
        'isbn' => '978-3-16-148410-0',
    ]);

    expect($book->copyright_text)->toBe('© 2026 Test Author');
    expect($book->dedication_text)->toBe('For my family');
    expect($book->epigraph_text)->toBe('"All we have to decide..."');
    expect($book->epigraph_attribution)->toBe('— J.R.R. Tolkien');
    expect($book->acknowledgment_text)->toBe('Thanks to everyone');
    expect($book->about_author_text)->toBe('Jane writes thrillers');
    expect($book->also_by_text)->toBe("Book One\nBook Two");
    expect($book->publisher_name)->toBe('Self Published');
    expect($book->isbn)->toBe('978-3-16-148410-0');
});

it('stores cover image path on book', function () {
    $book = Book::factory()->create([
        'cover_image_path' => 'covers/book-1.jpg',
    ]);

    expect($book->cover_image_path)->toBe('covers/book-1.jpg');
});

it('marks a chapter as epilogue', function () {
    $book = Book::factory()->create();
    $chapter = Chapter::factory()->create([
        'book_id' => $book->id,
        'is_epilogue' => true,
    ]);

    expect($chapter->is_epilogue)->toBeTrue();
});
