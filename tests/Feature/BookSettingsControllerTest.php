<?php

use App\Enums\Genre;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\License;
use App\Services\Export\ExportService;
use App\Services\WritingStyleService;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Native\Desktop\Dialog;

use function Pest\Laravel\mock;

test('book settings index redirects to the general page', function () {
    $book = Book::factory()->create();

    $this->get(route('books.settings', $book))
        ->assertRedirect(route('books.settings.general', $book));
});

test('general page renders the book identity fields', function () {
    $book = Book::factory()->withGenre(Genre::Fantasy, [Genre::Adventure])->create(['subtitle' => 'A Tale']);

    $this->get(route('books.settings.general', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/settings/general')
            ->where('book.title', $book->title)
            ->where('book.subtitle', 'A Tale')
            ->where('book.author', $book->author)
            ->where('book.language', 'de')
            ->where('book.genre', 'fantasy')
            ->where('book.secondary_genres', ['adventure'])
        );
});

test('general settings update persists identity fields', function () {
    $book = Book::factory()->create();

    $this->put(route('books.settings.general.update', $book), [
        'title' => 'New Title',
        'subtitle' => 'New Subtitle',
        'author' => 'New Author',
        'language' => 'en',
        'genre' => 'mystery',
        'secondary_genres' => ['crime'],
    ])->assertRedirect();

    $book->refresh();
    expect($book->title)->toBe('New Title')
        ->and($book->subtitle)->toBe('New Subtitle')
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

test('writing style update accepts a long manually pasted style', function () {
    $book = Book::factory()->create();
    $longStyle = str_repeat('a', 15000);

    $this->putJson(route('books.settings.writing-style.update', $book), [
        'writing_style_text' => $longStyle,
    ])->assertOk();

    expect($book->refresh()->writing_style_text)->toBe($longStyle);
});

test('writing style update rejects an over-limit style with a message', function () {
    $book = Book::factory()->create();

    $this->putJson(route('books.settings.writing-style.update', $book), [
        'writing_style_text' => str_repeat('a', 20001),
    ])->assertStatus(422)
        ->assertJsonValidationErrors('writing_style_text');
});

test('writing style regeneration rejects books with too little prose', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapters[0]->currentVersion()->update(['content' => '<p>Barely any prose here.</p>']);

    mock(WritingStyleService::class)->shouldNotReceive('extract');

    $this->postJson(route('books.settings.writing-style.regenerate', $book))
        ->assertUnprocessable();
});

test('writing style regeneration extracts from the unified sample and persists', function () {
    [$book, $chapters] = createBookWithChapters(4);
    foreach ($chapters as $i => $chapter) {
        $marker = ['firstmarker', 'secondmarker', 'thirdmarker', 'fourthmarker'][$i];
        $chapter->currentVersion()->update([
            'content' => '<p>'.trim(str_repeat("{$marker} ", 150)).'</p>',
        ]);
    }

    mock(WritingStyleService::class)
        ->shouldReceive('extract')
        ->once()
        ->withArgs(function (string $sample, Book $received) use ($book) {
            // Unified sampling: first three prose chapters only, tags stripped.
            return $received->is($book)
                && str_contains($sample, 'firstmarker')
                && str_contains($sample, 'thirdmarker')
                && ! str_contains($sample, 'fourthmarker')
                && ! str_contains($sample, '<p>');
        })
        ->andReturn(['tone' => 'spare and wintry']);

    $this->postJson(route('books.settings.writing-style.regenerate', $book))
        ->assertOk()
        ->assertJsonPath('writing_style_text', Book::formatWritingStyle(['tone' => 'spare and wintry']));

    $book->refresh();
    expect($book->writing_style)->toBe(['tone' => 'spare and wintry'])
        ->and($book->writing_style_text)->toBe('Tone: spare and wintry');
});

test('writing style prompt dismissal persists to the book', function () {
    $book = Book::factory()->create();

    expect($book->fresh()->writing_style_prompt_dismissed)->toBeFalse();

    $this->postJson(route('books.settings.writing-style.dismiss-prompt', $book))
        ->assertOk();

    expect($book->refresh()->writing_style_prompt_dismissed)->toBeTrue();
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

test('export settings update persists to the book', function () {
    $book = Book::factory()->create();

    $settings = [
        'format' => 'pdf',
        'template' => 'modern',
        'font_pairing' => 'classic-serif',
        'scene_break_style' => 'asterisks',
        'drop_caps' => false,
        'chapter_heading' => 'number',
        'include_act_breaks' => true,
        'show_page_numbers' => false,
        'trim_size' => '5x8',
        'font_size' => 12,
        'cmyk' => true,
        'bleed' => 3,
        'bleed_mode' => 'outer',
        'include_cover' => false,
        'front_matter' => ['title-page', 'toc'],
        'back_matter' => ['acknowledgments'],
        'excluded_chapter_ids' => [7],
    ];

    $this->putJson(route('books.settings.export-settings.update', $book), [
        'settings' => $settings,
    ])->assertOk();

    expect($book->refresh()->export_settings)->toBe($settings);
});

test('export settings update rejects invalid values', function (array $settings, string $errorKey) {
    $book = Book::factory()->create();

    $this->putJson(route('books.settings.export-settings.update', $book), [
        'settings' => $settings,
    ])->assertUnprocessable()->assertJsonValidationErrors($errorKey);
})->with([
    'unknown format' => [['format' => 'rtf'], 'settings.format'],
    'unknown trim size' => [['trim_size' => '9x9'], 'settings.trim_size'],
    'unknown key' => [['margin_color' => 'red'], 'settings'],
]);

test('export page exposes saved export settings', function () {
    $book = Book::factory()->create([
        'export_settings' => ['format' => 'pdf', 'trim_size' => 'a5'],
    ]);

    $this->get(route('books.settings.export', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('books/export')
            ->where('exportSettings.format', 'pdf')
            ->where('exportSettings.trim_size', 'a5')
        );
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
