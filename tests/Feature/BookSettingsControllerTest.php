<?php

use App\Models\Book;
use App\Models\License;
use App\Services\Export\ExportService;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Native\Desktop\Dialog;

test('writing style page redirects to unified settings', function () {
    $book = Book::factory()->create();

    $this->get(route('books.settings.writing-style', $book))
        ->assertRedirect('/settings');
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

test('prose pass rules page redirects to unified settings', function () {
    $book = Book::factory()->create();

    $this->get(route('books.settings.prose-pass-rules', $book))
        ->assertRedirect('/settings');
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
