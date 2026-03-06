<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Storyline;
use Illuminate\Http\UploadedFile;

function docxFixture(string $name): UploadedFile
{
    return new UploadedFile(
        path: __DIR__."/fixtures/{$name}",
        originalName: $name,
        mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        test: true,
    );
}

test('parse endpoint extracts chapters from a docx file', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $response = $this->postJson(route('books.import.parse', $book), [
        'files' => [
            [
                'file' => docxFixture('chapters.docx'),
                'storyline_name' => 'Main',
                'storyline_type' => 'main',
            ],
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'storylines')
        ->assertJsonCount(3, 'storylines.0.chapters')
        ->assertJsonPath('storylines.0.storyline_name', 'Main')
        ->assertJsonPath('storylines.0.storyline_type', 'main')
        ->assertJsonPath('storylines.0.chapters.0.number', 1)
        ->assertJsonPath('storylines.0.chapters.0.title', 'The Morning After')
        ->assertJsonPath('storylines.0.chapters.1.title', 'Echoes')
        ->assertJsonPath('storylines.0.chapters.2.title', 'The Garden Wall');
});

test('parse endpoint falls back to single chapter when no headings', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $response = $this->postJson(route('books.import.parse', $book), [
        'files' => [
            [
                'file' => docxFixture('no-headings.docx'),
                'storyline_name' => 'Parallel',
                'storyline_type' => 'parallel',
            ],
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'storylines.0.chapters')
        ->assertJsonPath('storylines.0.chapters.0.title', 'Full Document');
});

test('parse endpoint validates files are required', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.import.parse', $book), [
        'files' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('files');
});

test('parse endpoint validates file type is docx', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.import.parse', $book), [
        'files' => [
            [
                'file' => UploadedFile::fake()->create('test.pdf', 100, 'application/pdf'),
                'storyline_name' => 'Main',
                'storyline_type' => 'main',
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('files.0.file');
});

test('parser filters out empty chapters from consecutive headings', function () {
    $book = Book::factory()->create();
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $response = $this->postJson(route('books.import.parse', $book), [
        'files' => [
            [
                'file' => docxFixture('consecutive-headings.docx'),
                'storyline_name' => 'Main',
                'storyline_type' => 'main',
            ],
        ],
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(2, 'storylines.0.chapters')
        ->assertJsonPath('storylines.0.chapters.0.number', 1)
        ->assertJsonPath('storylines.0.chapters.0.title', 'Into the Woods')
        ->assertJsonPath('storylines.0.chapters.1.number', 2)
        ->assertJsonPath('storylines.0.chapters.1.title', 'The Clearing');
});

test('parser outputs HTML-wrapped paragraphs', function () {
    $parser = new \App\Services\DocxParserService;
    $result = $parser->parse(docxFixture('chapters.docx'));

    expect($result['chapters'][0]['content'])->toContain('<p>');
});

test('parser preserves inline formatting', function () {
    $parser = new \App\Services\DocxParserService;
    $result = $parser->parse(docxFixture('formatted.docx'));
    $content = $result['chapters'][0]['content'];

    expect($content)
        ->toContain('<strong>bold text</strong>')
        ->toContain('<em>italic text</em>')
        ->toContain('<u>underlined</u>')
        ->toContain('<strong><em>Bold and italic together.</em></strong>');
});

test('parser converts scene breaks to hr', function () {
    $parser = new \App\Services\DocxParserService;
    $result = $parser->parse(docxFixture('formatted.docx'));
    $content = $result['chapters'][0]['content'];

    expect($content)->toContain('<hr>');
});

test('parser converts blockquote styles', function () {
    $parser = new \App\Services\DocxParserService;
    $result = $parser->parse(docxFixture('formatted.docx'));
    $content = $result['chapters'][0]['content'];

    expect($content)->toContain('<blockquote><p>This is a blockquote paragraph.</p></blockquote>');
});

test('parser escapes special characters', function () {
    $parser = new \App\Services\DocxParserService;
    $result = $parser->parse(docxFixture('formatted.docx'));
    $content = $result['chapters'][0]['content'];

    expect($content)
        ->toContain('Tom &amp; Jerry')
        ->toContain('&lt;script&gt;');
});

test('parser preserves line breaks', function () {
    $parser = new \App\Services\DocxParserService;
    $result = $parser->parse(docxFixture('formatted.docx'));
    $content = $result['chapters'][0]['content'];

    expect($content)->toContain('Line one<br>line two after break.');
});

test('fallback result produces HTML paragraphs', function () {
    $parser = new \App\Services\DocxParserService;
    $result = $parser->parse(docxFixture('no-headings.docx'));
    $content = $result['chapters'][0]['content'];

    expect($content)
        ->toContain('<p>')
        ->toContain('</p>');
});

test('confirm import accepts excluded chapters with empty content', function () {
    $book = Book::factory()->create();

    $this->post(route('books.import.confirm', $book), [
        'storylines' => [
            [
                'name' => 'Main',
                'type' => 'main',
                'chapters' => [
                    [
                        'title' => 'Real Chapter',
                        'content' => 'Some actual content here.',
                        'word_count' => 4,
                        'included' => true,
                    ],
                    [
                        'title' => 'Empty Decorative Heading',
                        'content' => '',
                        'word_count' => 0,
                        'included' => false,
                    ],
                ],
            ],
        ],
    ])->assertRedirect(route('books.editor', $book));

    expect(Chapter::query()->where('book_id', $book->id)->count())->toBe(1);
    expect(Chapter::query()->where('book_id', $book->id)->first()->title)->toBe('Real Chapter');
});

test('confirm import creates storylines, chapters, and versions', function () {
    $book = Book::factory()->create();

    $this->post(route('books.import.confirm', $book), [
        'storylines' => [
            [
                'name' => 'Main',
                'type' => 'main',
                'chapters' => [
                    [
                        'title' => 'The Morning After',
                        'content' => 'The sun rose slowly.',
                        'word_count' => 5,
                        'included' => true,
                    ],
                    [
                        'title' => 'Echoes',
                        'content' => 'The hallway stretched.',
                        'word_count' => 3,
                        'included' => true,
                    ],
                ],
            ],
        ],
    ])->assertRedirect(route('books.editor', $book));

    expect(Storyline::query()->where('book_id', $book->id)->where('name', 'Main')->exists())->toBeTrue();
    expect(Chapter::query()->where('book_id', $book->id)->count())->toBe(2);
    expect(ChapterVersion::query()->whereHas('chapter', fn ($q) => $q->where('book_id', $book->id))->count())->toBe(2);

    $chapter = Chapter::query()->where('book_id', $book->id)->where('title', 'The Morning After')->first();
    expect($chapter)->not->toBeNull()
        ->and($chapter->reader_order)->toBe(0)
        ->and($chapter->status->value)->toBe('draft')
        ->and($chapter->currentVersion->content)->toBe('The sun rose slowly.')
        ->and($chapter->currentVersion->source->value)->toBe('original');
});

test('confirm import skips excluded chapters', function () {
    $book = Book::factory()->create();

    $this->post(route('books.import.confirm', $book), [
        'storylines' => [
            [
                'name' => 'Backstory',
                'type' => 'backstory',
                'chapters' => [
                    [
                        'title' => 'Keep This',
                        'content' => 'Content here.',
                        'word_count' => 2,
                        'included' => true,
                    ],
                    [
                        'title' => 'Skip This',
                        'content' => 'More content.',
                        'word_count' => 2,
                        'included' => false,
                    ],
                ],
            ],
        ],
    ])->assertRedirect();

    expect(Chapter::query()->where('book_id', $book->id)->count())->toBe(1);
    expect(Chapter::query()->where('book_id', $book->id)->where('title', 'Skip This')->exists())->toBeFalse();
});

test('confirm import auto-normalizes content', function () {
    $book = Book::factory()->create(['language' => 'en']);

    $this->post(route('books.import.confirm', $book), [
        'storylines' => [
            [
                'name' => 'Main',
                'type' => 'main',
                'chapters' => [
                    [
                        'title' => 'Chapter One',
                        'content' => '<p>She said "hello" -- and smiled...</p>',
                        'word_count' => 5,
                        'included' => true,
                    ],
                ],
            ],
        ],
    ])->assertRedirect();

    $chapter = Chapter::query()->where('book_id', $book->id)->first();
    $content = $chapter->currentVersion->content;

    // Smart quotes should be applied
    expect($content)->not->toContain('"hello"');
    // Em dashes should replace double hyphens
    expect($content)->not->toContain('--');
});

test('confirm import creates multiple storylines', function () {
    $book = Book::factory()->create();

    $this->post(route('books.import.confirm', $book), [
        'storylines' => [
            [
                'name' => 'Main',
                'type' => 'main',
                'chapters' => [
                    ['title' => 'Ch 1', 'content' => 'Content.', 'word_count' => 1, 'included' => true],
                ],
            ],
            [
                'name' => 'Parallel',
                'type' => 'parallel',
                'chapters' => [
                    ['title' => 'P 1', 'content' => 'Parallel content.', 'word_count' => 2, 'included' => true],
                ],
            ],
        ],
    ])->assertRedirect();

    $storylines = Storyline::query()->where('book_id', $book->id)->orderBy('sort_order')->get();
    expect($storylines)->toHaveCount(2)
        ->and($storylines[0]->name)->toBe('Main')
        ->and($storylines[0]->sort_order)->toBe(0)
        ->and($storylines[1]->name)->toBe('Parallel')
        ->and($storylines[1]->sort_order)->toBe(1);
});
