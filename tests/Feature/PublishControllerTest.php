<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->book = Book::factory()->create();
    $this->actingAs($this->user);
});

it('renders the publish page', function () {
    $response = $this->get(route('books.publish', $this->book));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('books/publish')
        ->has('book')
        ->has('chapters')
    );
});

it('updates publish settings', function () {
    $response = $this->put(route('books.publish.update', $this->book), [
        'copyright_text' => '© 2026 Test Author. All rights reserved.',
        'dedication_text' => 'For my family',
        'epigraph_text' => 'A great quote',
        'epigraph_attribution' => '— Famous Author',
        'acknowledgment_text' => 'Thanks to everyone',
        'about_author_text' => 'I write books.',
        'also_by_text' => "Book One\nBook Two",
        'publisher_name' => 'Self Published',
        'isbn' => '978-3-16-148410-0',
    ]);

    $response->assertRedirect();

    $this->book->refresh();
    expect($this->book->copyright_text)->toBe('© 2026 Test Author. All rights reserved.');
    expect($this->book->dedication_text)->toBe('For my family');
    expect($this->book->publisher_name)->toBe('Self Published');
});

it('uploads a cover image', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->image('cover.jpg', 2560, 1600);

    $response = $this->post(route('books.publish.cover', $this->book), [
        'cover_image' => $file,
    ]);

    $response->assertRedirect();

    $this->book->refresh();
    expect($this->book->cover_image_path)->not->toBeNull();
    Storage::disk('local')->assertExists($this->book->cover_image_path);
});

it('rejects a raw PDF upload at the backend', function () {
    Storage::fake('local');

    $pdf = UploadedFile::fake()->create('cover.pdf', 200, 'application/pdf');

    $response = $this->post(route('books.publish.cover', $this->book), [
        'cover_image' => $pdf,
    ]);

    $response->assertSessionHasErrors('cover_image');

    $this->book->refresh();
    expect($this->book->cover_image_path)->toBeNull();
});

it('deletes a cover image', function () {
    Storage::fake('local');
    Storage::disk('local')->put('covers/old.jpg', 'fake');
    $this->book->update(['cover_image_path' => 'covers/old.jpg']);

    $response = $this->delete(route('books.publish.cover.delete', $this->book));

    $response->assertRedirect();

    $this->book->refresh();
    expect($this->book->cover_image_path)->toBeNull();
});

it('marks a chapter as epilogue', function () {
    $chapter = Chapter::factory()->create(['book_id' => $this->book->id]);

    $response = $this->put(route('books.publish.epilogue', $this->book), [
        'chapter_id' => $chapter->id,
    ]);

    $response->assertRedirect();

    $chapter->refresh();
    expect($chapter->is_epilogue)->toBeTrue();
});

it('serves a cover image', function () {
    Storage::fake('local');
    Storage::disk('local')->put('covers/test.jpg', 'fake-image-data');
    $this->book->update(['cover_image_path' => 'covers/test.jpg']);

    $response = $this->get(route('books.publish.cover.serve', $this->book));

    $response->assertOk();
});

it('returns 404 for missing cover image', function () {
    $response = $this->get(route('books.publish.cover.serve', $this->book));

    $response->assertNotFound();
});

it('includes cover_image_url in publish page props', function () {
    $this->book->update(['cover_image_path' => 'covers/test.jpg']);

    $response = $this->get(route('books.publish', $this->book));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('books/publish')
        ->where('book.cover_image_url', route('books.publish.cover.serve', $this->book))
    );
});

it('unmarks epilogue when null chapter_id sent', function () {
    $chapter = Chapter::factory()->create([
        'book_id' => $this->book->id,
        'is_epilogue' => true,
    ]);

    $response = $this->put(route('books.publish.epilogue', $this->book), [
        'chapter_id' => null,
    ]);

    $response->assertRedirect();

    $chapter->refresh();
    expect($chapter->is_epilogue)->toBeFalse();
});
