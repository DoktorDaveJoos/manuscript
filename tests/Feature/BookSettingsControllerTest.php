<?php

use App\Enums\Genre;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Services\Export\ExportService;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Native\Desktop\Dialog;

test('book settings index redirects to the general page', function () {
    $book = Book::factory()->create();

    $this->get(route('books.settings', $book))
        ->assertRedirect(route('books.settings.general', $book));
});

test('general page renders the book identity fields', function () {
    $book = Book::factory()->withGenre(Genre::Fantasy, [Genre::Adventure])->create();

    $this->get(route('books.settings.general', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/settings/general')
            ->where('book.title', $book->title)
            ->where('book.author', $book->author)
            ->where('book.language', 'de')
            ->where('book.genre', 'fantasy')
            ->where('book.secondary_genres', ['adventure'])
            ->has('genres')
        );
});

test('general settings update persists identity fields', function () {
    $book = Book::factory()->create();

    $this->put(route('books.settings.general.update', $book), [
        'title' => 'New Title',
        'author' => 'New Author',
        'language' => 'en',
        'genre' => 'mystery',
        'secondary_genres' => ['crime'],
    ])->assertRedirect();

    $book->refresh();
    expect($book->title)->toBe('New Title')
        ->and($book->author)->toBe('New Author')
        ->and($book->language)->toBe('en')
        ->and($book->genre)->toBe(Genre::Mystery)
        ->and($book->secondary_genres)->toBe(['crime']);
});

test('general settings update rejects an unknown genre', function () {
    $book = Book::factory()->create();

    $this->putJson(route('books.settings.general.update', $book), [
        'title' => 'New Title',
        'language' => 'en',
        'genre' => 'cooking',
    ])->assertUnprocessable()->assertJsonValidationErrors('genre');
});

test('writing style page renders the book writing style', function () {
    $book = Book::factory()->create(['writing_style_text' => 'Sparse and cold.']);

    $this->get(route('books.settings.writing-style', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/settings/writing-style')
            ->where('book.writing_style_text', 'Sparse and cold.')
            ->where('writing_style_display', 'Sparse and cold.')
        );
});

test('writing style update persists to the book', function () {
    $book = Book::factory()->create();

    $this->putJson(route('books.settings.writing-style.update', $book), [
        'writing_style_text' => 'Lyrical, slow-burning prose.',
    ])->assertOk();

    expect($book->refresh()->writing_style_text)->toBe('Lyrical, slow-burning prose.');
});

test('prose rules page renders the book rules', function () {
    $rules = Book::defaultProsePassRules();
    $rules[0]['enabled'] = false;
    $book = Book::factory()->create(['prose_pass_rules' => $rules]);

    $this->get(route('books.settings.prose-rules', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/settings/prose-rules')
            ->where('rules.0.enabled', false)
        );
});

test('prose rules update persists to the book', function () {
    $book = Book::factory()->create();
    $rules = Book::defaultProsePassRules();
    $rules[0]['enabled'] = false;

    $this->putJson(route('books.settings.prose-rules.update', $book), [
        'rules' => $rules,
    ])->assertOk();

    expect($book->refresh()->prose_pass_rules[0]['enabled'])->toBeFalse();
});

test('legacy prose-pass-rules url redirects to the prose rules page', function () {
    $book = Book::factory()->create();

    $this->get(route('books.settings.prose-pass-rules', $book))
        ->assertRedirect(route('books.settings.prose-rules', $book));
});

test('publishing page renders book metadata and chapters', function () {
    $book = Book::factory()->create(['klappentext' => 'A hook.']);
    Chapter::factory()->create(['book_id' => $book->id]);

    $this->get(route('books.settings.publishing', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/settings/publishing')
            ->where('book.klappentext', 'A hook.')
            ->has('chapters', 1)
        );
});

test('cover page renders cover state and trim sizes', function () {
    $book = Book::factory()->create([
        'cover_image_path' => 'covers/test.jpg',
        'cover_settings' => ['title' => 'Saved', 'trim_size' => '13x19cm'],
        'klappentext' => 'Back panel hook.',
    ]);

    $this->get(route('books.settings.cover', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/settings/cover')
            ->has('trimSizes')
            ->where('book.cover_settings.title', 'Saved')
            ->where('book.klappentext', 'Back panel hook.')
            ->where('book.cover_image_url', fn ($url) => str_starts_with(
                (string) $url,
                route('books.publish.cover.serve', $book)
            ))
            ->has('book.cover_genre')
        );
});

test('native export surfaces exporter error in JSON response and reports it', function () {
    config(['nativephp-internal.running' => true]);
    Exceptions::fake();

    $book = Book::factory()->create();

    $service = Mockery::mock(ExportService::class);
    $service->shouldReceive('exportToPath')
        ->once()
        ->andThrow(new RuntimeException('PhpWord exploded on docx'));
    app()->instance(ExportService::class, $service);

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'docx',
        'scope' => 'full',
    ])
        ->assertStatus(500)
        ->assertJsonPath('error', 'PhpWord exploded on docx');

    Exceptions::assertReported(RuntimeException::class);
});

test('native export surfaces dialog error in JSON response and reports it', function () {
    config(['nativephp-internal.running' => true]);
    Exceptions::fake();

    $book = Book::factory()->create();

    $dialog = Mockery::mock(Dialog::class);
    $dialog->shouldReceive('title->defaultPath->filter->button->asSheet->save')
        ->andThrow(new RuntimeException('Window unavailable'));
    app()->instance(Dialog::class, $dialog);

    $this->postJson(route('books.settings.export.run', $book), [
        'format' => 'docx',
        'scope' => 'full',
    ])
        ->assertStatus(500)
        ->assertJsonPath('error', 'Window unavailable');

    Exceptions::assertReported(RuntimeException::class);
});

test('preview endpoint never embeds cover even when include_cover is true', function () {
    License::factory()->create();
    Storage::fake('local');

    $pngBytes = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNgAAIAAAUAAeImBZsAAAAASUVORK5CYII='
    );
    Storage::disk('local')->put('covers/test.png', $pngBytes);

    [$book, $chapters] = createBookWithChapters(1);
    $book->update(['cover_image_path' => 'covers/test.png']);
    $chapter = $chapters[0];

    $response = $this->postJson(route('books.export.preview', $book), [
        'format' => 'pdf',
        'chapter_ids' => [$chapter->id],
        'include_cover' => true,
        'include_chapter_titles' => true,
        'show_page_numbers' => false,
        'include_act_breaks' => false,
        'template' => 'classic',
        'trim_size' => '6x9',
        'font_size' => 11,
    ]);

    $response->assertOk();
    $pdf = base64_decode($response->json('pdf'));

    expect($pdf)->not->toContain('/Subtype /Image');
    expect($pdf)->not->toContain('/Subtype/Image');
});

test('export page loads with chapters and trim sizes', function () {
    $book = Book::factory()->create();

    $this->get(route('books.settings.export', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/export')
            ->has('book')
            ->has('storylines')
            ->has('chapters')
            ->has('trimSizes')
            ->has('acts')
        );
});
