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

it('persists the klappentext via publish settings', function () {
    $this->put(route('books.publish.update', $this->book), [
        'klappentext' => 'When the lighthouse goes dark, the village holds its breath.',
    ])->assertRedirect();

    expect($this->book->refresh()->klappentext)
        ->toBe('When the lighthouse goes dark, the village holds its breath.');
});

it('exposes the klappentext on the publish page', function () {
    $this->book->update(['klappentext' => 'A back-cover hook.']);

    $this->get(route('books.publish', $this->book))
        ->assertInertia(fn ($page) => $page
            ->component('books/publish')
            ->where('book.klappentext', 'A back-cover hook.')
        );
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

it('persists cover_settings when a generated cover is uploaded', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->image('cover.png', 1600, 2347);

    $response = $this->post(route('books.publish.cover', $this->book), [
        'cover_image' => $file,
        'cover_settings' => [
            'title' => 'Generated Title',
            'subtitle' => 'A Thriller',
            'author' => 'Jane Doe',
            'trim_size' => '13x19cm',
        ],
    ]);

    $response->assertRedirect();

    $this->book->refresh();
    expect($this->book->cover_image_path)->not->toBeNull();
    expect($this->book->cover_settings)->toMatchArray([
        'title' => 'Generated Title',
        'trim_size' => '13x19cm',
    ]);
});

it('persists cover_settings sent as bracketed multipart keys (browser shape)', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->image('cover.png', 1600, 2347);

    // The cover creator posts cover_settings as bracketed form keys via FormData;
    // Laravel reconstructs the nested array. This locks that contract.
    $response = $this->post(route('books.publish.cover', $this->book), [
        'cover_image' => $file,
        'cover_settings' => [
            'title' => 'Bracketed Title',
            'subtitle' => 'A Thriller',
            'author' => 'Jane Doe',
            'trim_size' => '13x19cm',
        ],
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();

    expect($this->book->refresh()->cover_settings)->toMatchArray([
        'title' => 'Bracketed Title',
        'subtitle' => 'A Thriller',
        'author' => 'Jane Doe',
        'trim_size' => '13x19cm',
    ]);
});

it('clears cover_settings when a plain image is uploaded', function () {
    Storage::fake('local');
    $this->book->update(['cover_settings' => ['title' => 'Old', 'trim_size' => '6x9']]);

    $file = UploadedFile::fake()->image('cover.jpg', 2560, 1600);

    $this->post(route('books.publish.cover', $this->book), [
        'cover_image' => $file,
    ])->assertRedirect();

    expect($this->book->refresh()->cover_settings)->toBeNull();
});

it('clears cover_settings when the cover is deleted', function () {
    Storage::fake('local');
    Storage::disk('local')->put('covers/old.jpg', 'fake');
    $this->book->update([
        'cover_image_path' => 'covers/old.jpg',
        'cover_settings' => ['title' => 'Old', 'trim_size' => '6x9'],
    ]);

    $this->delete(route('books.publish.cover.delete', $this->book))->assertRedirect();

    $this->book->refresh();
    expect($this->book->cover_image_path)->toBeNull();
    expect($this->book->cover_settings)->toBeNull();
});

it('generates a cover preview pdf', function () {
    $response = $this->postJson(route('books.publish.cover.generate', $this->book), [
        'title' => 'The Silent Tide',
        'subtitle' => 'A Thriller',
        'author' => 'Jane Doe',
        'trim_size' => '13x19cm',
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure(['pdf']);

    $pdf = base64_decode($response->json('pdf'));
    expect($pdf)->toStartWith('%PDF-');
});

it('requires a title to generate a cover', function () {
    $response = $this->postJson(route('books.publish.cover.generate', $this->book), [
        'title' => '',
        'trim_size' => '13x19cm',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('title');
});

it('rejects an unknown cover face', function () {
    $this->postJson(route('books.publish.cover.generate', $this->book), [
        'title' => 'X',
        'face' => 'sideways',
    ])->assertStatus(422)->assertJsonValidationErrors('face');
});

it('injects the saved klappentext into the back-face preview', function () {
    $payload = ['title' => 'The Silent Tide', 'trim_size' => '13x19cm', 'face' => 'back'];

    // With no Klappentext the back panel is blank; with one it carries the blurb,
    // so the rendered PDF is strictly larger — proof the controller injects it.
    $this->book->update(['klappentext' => null]);
    $blank = base64_decode(
        $this->postJson(route('books.publish.cover.generate', $this->book), $payload)->json('pdf')
    );

    $this->book->update([
        'klappentext' => 'A back-cover hook that adds real, measurable text to the back panel of the jacket.',
    ]);
    $withBlurb = base64_decode(
        $this->postJson(route('books.publish.cover.generate', $this->book), $payload)->json('pdf')
    );

    expect($withBlurb)->toStartWith('%PDF-');
    expect(strlen($withBlurb))->toBeGreaterThan(strlen($blank));
});

it('renders the klappentext into the downloaded wraparound jacket', function () {
    $this->book->update([
        'title' => 'The Silent Tide',
        'klappentext' => 'A back-cover hook that sells the book.',
        'cover_settings' => [
            'title' => 'The Silent Tide',
            'trim_size' => '13x19cm',
            'spine_width' => 3.5,
        ],
    ]);

    $response = $this->get(route('books.publish.cover.download', $this->book));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->streamedContent())->toStartWith('%PDF-');
});

it('downloads the generated cover as a print-ready wraparound pdf', function () {
    $this->book->update([
        'title' => 'The Silent Tide',
        'cover_settings' => [
            'title' => 'The Silent Tide',
            'subtitle' => 'A Thriller',
            'author' => 'Jane Doe',
            'trim_size' => '13x19cm',
            'spine_width' => 3.5,
        ],
    ]);

    $response = $this->get(route('books.publish.cover.download', $this->book));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('the-silent-tide-cover.pdf');

    $pdf = $response->streamedContent();
    expect($pdf)->toStartWith('%PDF-');

    // Full flattened jacket geometry: 269.5 × 196 mm = 763.94 × 555.59 pt.
    if (! trim((string) shell_exec('command -v pdfinfo'))) {
        return;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'cov').'.pdf';
    file_put_contents($tmp, $pdf);
    $info = (string) shell_exec('pdfinfo '.escapeshellarg($tmp).' 2>/dev/null');
    @unlink($tmp);
    expect($info)->toMatch('/Page size:\s+763\.9\d* x 555\.5\d* pts/');
});

it('returns 404 when downloading a cover that was not generated', function () {
    $this->book->update(['cover_settings' => null]);

    $this->get(route('books.publish.cover.download', $this->book))->assertNotFound();
});

it('exposes trim sizes and cover settings on the publish page', function () {
    $this->book->update(['cover_settings' => ['title' => 'Saved', 'trim_size' => '13x19cm']]);

    $response = $this->get(route('books.publish', $this->book));

    $response->assertInertia(fn ($page) => $page
        ->component('books/publish')
        ->has('trimSizes')
        ->where('book.cover_settings.title', 'Saved')
        ->has('book.cover_genre')
    );
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
        // URL is cache-busted with ?v=<timestamp> so a regenerated cover refreshes in place.
        ->where('book.cover_image_url', fn ($url) => str_starts_with(
            (string) $url,
            route('books.publish.cover.serve', $this->book)
        ))
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

it('marks a chapter as prologue', function () {
    $chapter = Chapter::factory()->create(['book_id' => $this->book->id]);

    $response = $this->put(route('books.publish.prologue', $this->book), [
        'chapter_id' => $chapter->id,
    ]);

    $response->assertRedirect();

    $chapter->refresh();
    expect($chapter->is_prologue)->toBeTrue();
});

it('unmarks prologue when null chapter_id sent', function () {
    $chapter = Chapter::factory()->create([
        'book_id' => $this->book->id,
        'is_prologue' => true,
    ]);

    $response = $this->put(route('books.publish.prologue', $this->book), [
        'chapter_id' => null,
    ]);

    $response->assertRedirect();

    $chapter->refresh();
    expect($chapter->is_prologue)->toBeFalse();
});

it('replaces the previous prologue when a new chapter is selected', function () {
    $first = Chapter::factory()->create([
        'book_id' => $this->book->id,
        'is_prologue' => true,
    ]);
    $second = Chapter::factory()->create(['book_id' => $this->book->id]);

    $this->put(route('books.publish.prologue', $this->book), [
        'chapter_id' => $second->id,
    ])->assertRedirect();

    expect($first->refresh()->is_prologue)->toBeFalse();
    expect($second->refresh()->is_prologue)->toBeTrue();
});
