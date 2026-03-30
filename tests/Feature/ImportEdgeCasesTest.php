<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Storyline;
use App\Services\DocxParserService;
use App\Services\Parsers\DocumentParserFactory;
use App\Services\Parsers\MarkdownParserService;
use App\Services\Parsers\TxtParserService;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

// ─── Helpers ─────────────────────────────────────────────────────────────

/** @var list<string> */
$tempFiles = [];

function tempFile(string $content, string $name, string $mime = 'text/plain'): UploadedFile
{
    global $tempFiles;
    $path = tempnam(sys_get_temp_dir(), 'import_');
    file_put_contents($path, $content);
    $tempFiles[] = $path;

    return new UploadedFile($path, $name, $mime, null, true);
}

afterEach(function () {
    global $tempFiles;
    foreach ($tempFiles as $path) {
        @unlink($path);
    }
    $tempFiles = [];
});

function parseFiles(Book $book, array $files, bool $merge = false): TestResponse
{
    $payload = ['files' => $files];
    if ($merge) {
        $payload['merge_into_single_storyline'] = true;
    }

    return test()->postJson(route('books.import.parse', $book), $payload);
}

// ─── Corrupted / Invalid Files ───────────────────────────────────────────

test('corrupted zip files fall back to empty single chapter', function (string $ext, string $mime) {
    $book = Book::factory()->create();
    $file = tempFile('this is not a valid zip file', "corrupt.{$ext}", $mime);

    parseFiles($book, [[
        'file' => $file,
        'storyline_name' => 'Main',
        'storyline_type' => 'main',
    ]])->assertSuccessful()
        ->assertJsonPath('storylines.0.chapters.0.title', 'Full Document')
        ->assertJsonPath('storylines.0.chapters.0.word_count', 0);
})->with([
    'docx' => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'odt' => ['odt', 'application/vnd.oasis.opendocument.text'],
]);

// ─── Empty / Whitespace-Only Files ──────────────────────────────────────

test('empty txt file returns fallback single chapter with zero words', function () {
    $book = Book::factory()->create();
    $file = tempFile('', 'empty.txt');

    parseFiles($book, [[
        'file' => $file,
        'storyline_name' => 'Main',
        'storyline_type' => 'main',
    ]])->assertSuccessful()
        ->assertJsonCount(1, 'storylines.0.chapters')
        ->assertJsonPath('storylines.0.chapters.0.title', 'Full Document')
        ->assertJsonPath('storylines.0.chapters.0.word_count', 0);
});

test('whitespace-only txt file returns fallback with zero words', function () {
    $book = Book::factory()->create();
    $file = tempFile("   \n\n   \t\t\n  ", 'spaces.txt');

    parseFiles($book, [[
        'file' => $file,
        'storyline_name' => 'Main',
        'storyline_type' => 'main',
    ]])->assertSuccessful()
        ->assertJsonPath('storylines.0.chapters.0.title', 'Full Document')
        ->assertJsonPath('storylines.0.chapters.0.word_count', 0);
});

test('empty markdown file returns fallback single chapter', function () {
    $book = Book::factory()->create();
    $file = tempFile('', 'empty.md', 'text/markdown');

    parseFiles($book, [[
        'file' => $file,
        'storyline_name' => 'Main',
        'storyline_type' => 'main',
    ]])->assertSuccessful()
        ->assertJsonPath('storylines.0.chapters.0.title', 'Full Document');
});

// ─── Files With Only Headings (No Body) ─────────────────────────────────

test('txt file with only chapter headings and no content falls back', function () {
    $content = "Chapter 1: Title One\n\nChapter 2: Title Two\n\nChapter 3: Title Three";
    $file = tempFile($content, 'headings-only.txt');

    $parser = new TxtParserService;
    $result = $parser->parse($file);

    expect($result['chapters'])->toHaveCount(1)
        ->and($result['chapters'][0]['title'])->toBe('Full Document');
});

test('markdown with only headings and no body text falls back', function () {
    $content = "# Chapter One\n\n# Chapter Two\n\n# Chapter Three";
    $file = tempFile($content, 'headings-only.md', 'text/markdown');

    $parser = new MarkdownParserService;
    $result = $parser->parse($file);

    expect($result['chapters'])->toHaveCount(1)
        ->and($result['chapters'][0]['title'])->toBe('Full Document');
});

// ─── Unsupported File Extensions ────────────────────────────────────────

test('unsupported file extensions are rejected by validation', function (string $ext, string $mime) {
    $book = Book::factory()->create();

    parseFiles($book, [[
        'file' => UploadedFile::fake()->create("manuscript.{$ext}", 100, $mime),
        'storyline_name' => 'Main',
        'storyline_type' => 'main',
    ]])->assertUnprocessable()
        ->assertJsonValidationErrors('files.0.file');
})->with([
    'pdf' => ['pdf', 'application/pdf'],
    'rtf' => ['rtf', 'application/rtf'],
    'html' => ['html', 'text/html'],
    'epub' => ['epub', 'application/epub+zip'],
]);

// ─── Validation Edge Cases ──────────────────────────────────────────────

test('file larger than 10MB is rejected', function () {
    $book = Book::factory()->create();

    parseFiles($book, [[
        'file' => UploadedFile::fake()->create('huge.docx', 11000, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        'storyline_name' => 'Main',
        'storyline_type' => 'main',
    ]])->assertUnprocessable()
        ->assertJsonValidationErrors('files.0.file');
});

test('missing storyline_name is rejected', function () {
    $book = Book::factory()->create();

    parseFiles($book, [[
        'file' => tempFile('Some text', 'test.txt'),
        'storyline_type' => 'main',
    ]])->assertUnprocessable()
        ->assertJsonValidationErrors('files.0.storyline_name');
});

test('missing storyline_type is rejected', function () {
    $book = Book::factory()->create();

    parseFiles($book, [[
        'file' => tempFile('Some text', 'test.txt'),
        'storyline_name' => 'Main',
    ]])->assertUnprocessable()
        ->assertJsonValidationErrors('files.0.storyline_type');
});

test('invalid storyline_type is rejected', function () {
    $book = Book::factory()->create();

    parseFiles($book, [[
        'file' => tempFile('Some text', 'test.txt'),
        'storyline_name' => 'Main',
        'storyline_type' => 'nonexistent',
    ]])->assertUnprocessable()
        ->assertJsonValidationErrors('files.0.storyline_type');
});

test('missing file field in entry is rejected', function () {
    $book = Book::factory()->create();

    parseFiles($book, [[
        'storyline_name' => 'Main',
        'storyline_type' => 'main',
    ]])->assertUnprocessable()
        ->assertJsonValidationErrors('files.0.file');
});

// ─── German Chapter Patterns ────────────────────────────────────────────

test('txt parser detects German chapter headings (Kapitel)', function () {
    $content = "Kapitel 1: Der Anfang\n\nEs war einmal.\n\nKapitel 2: Die Mitte\n\nDie Geschichte ging weiter.";
    $file = tempFile($content, 'german.txt');

    $parser = new TxtParserService;
    $result = $parser->parse($file);

    expect($result['chapters'])->toHaveCount(2)
        ->and($result['chapters'][0]['title'])->toBe('Der Anfang')
        ->and($result['chapters'][1]['title'])->toBe('Die Mitte');
});

test('txt parser detects German part headings (Teil)', function () {
    $content = "Teil 1: Frühling\n\nDie Blumen blühen.\n\nTeil 2: Sommer\n\nDie Sonne scheint.";
    $file = tempFile($content, 'german-teil.txt');

    $parser = new TxtParserService;
    $result = $parser->parse($file);

    expect($result['chapters'])->toHaveCount(2)
        ->and($result['chapters'][0]['title'])->toBe('Frühling')
        ->and($result['chapters'][1]['title'])->toBe('Sommer');
});

// ─── Unicode Content ────────────────────────────────────────────────────

test('txt parser handles unicode content correctly', function () {
    $content = "Chapter 1: 日本語タイトル\n\nこれは日本語のテキストです。\n\nChapter 2: 中文标题\n\n这是中文文本。";
    $file = tempFile($content, 'unicode.txt');

    $parser = new TxtParserService;
    $result = $parser->parse($file);

    expect($result['chapters'])->toHaveCount(2)
        ->and($result['chapters'][0]['title'])->toBe('日本語タイトル')
        ->and($result['chapters'][0]['content'])->toContain('これは日本語のテキストです。')
        ->and($result['chapters'][1]['title'])->toBe('中文标题');
});

test('markdown parser handles unicode content', function () {
    $content = "# Глава первая\n\nОна шла по улице.\n\n# Глава вторая\n\nОн ждал.";
    $file = tempFile($content, 'cyrillic.md', 'text/markdown');

    $parser = new MarkdownParserService;
    $result = $parser->parse($file);

    expect($result['chapters'])->toHaveCount(2)
        ->and($result['chapters'][0]['title'])->toBe('Глава первая')
        ->and($result['chapters'][1]['title'])->toBe('Глава вторая');
});

test('txt parser handles emoji content', function () {
    $content = "Chapter 1: The Beginning 🌅\n\nOnce upon a time... 🏰\n\nChapter 2: The End 🌙\n\nAnd they lived happily ever after. 🎉";
    $file = tempFile($content, 'emoji.txt');

    $parser = new TxtParserService;
    $result = $parser->parse($file);

    expect($result['chapters'])->toHaveCount(2)
        ->and($result['chapters'][0]['content'])->toContain('🏰');
});

// ─── Encoding Detection ────────────────────────────────────────────────

test('txt parser auto-detects Windows-1252 encoding', function () {
    $content = "Chapter 1: Caf\xe9\n\nShe ordered a caf\xe9 au lait.";
    $file = tempFile($content, 'win1252.txt');

    $parser = new TxtParserService;
    $result = $parser->parse($file);

    expect($result['chapters'][0]['content'])->toContain('café');
});

// ─── Mixed Line Endings ─────────────────────────────────────────────────

test('txt parser normalizes mixed line endings', function () {
    $content = "Chapter 1: Mixed\r\n\r\nWindows paragraph.\r\rOld mac paragraph.\n\nUnix paragraph.";
    $file = tempFile($content, 'mixed-endings.txt');

    $parser = new TxtParserService;
    $result = $parser->parse($file);

    expect($result['chapters'])->toHaveCount(1)
        ->and($result['chapters'][0]['title'])->toBe('Mixed');
});

// ─── .markdown Extension ────────────────────────────────────────────────

test('parse endpoint accepts .markdown extension', function () {
    $book = Book::factory()->create();
    $file = tempFile("# First\n\nContent here.", 'story.markdown', 'text/markdown');

    parseFiles($book, [[
        'file' => $file,
        'storyline_name' => 'Main',
        'storyline_type' => 'main',
    ]])->assertSuccessful()
        ->assertJsonPath('storylines.0.chapters.0.title', 'First');
});

// ─── Scene Break Patterns ───────────────────────────────────────────────

test('txt parser recognizes various scene break patterns', function (string $pattern) {
    $content = "Chapter 1: Test\n\nBefore the break.\n\n{$pattern}\n\nAfter the break.";
    $file = tempFile($content, 'breaks.txt');

    $parser = new TxtParserService;
    $result = $parser->parse($file);

    expect($result['chapters'][0]['content'])->toContain('<hr>');
})->with([
    '***',
    '---',
    '* * *',
    '###',
    '~~~',
    '- - -',
]);

// ─── Multiple Files Without Merge ───────────────────────────────────────

test('multiple files create separate storylines without merge', function () {
    $book = Book::factory()->create();

    parseFiles($book, [
        [
            'file' => tempFile("Chapter 1: Alpha\n\nAlpha content.", 'alpha.txt'),
            'storyline_name' => 'Main Plot',
            'storyline_type' => 'main',
        ],
        [
            'file' => tempFile("Chapter 1: Beta\n\nBeta content.", 'beta.txt'),
            'storyline_name' => 'Backstory',
            'storyline_type' => 'backstory',
        ],
    ])->assertSuccessful()
        ->assertJsonCount(2, 'storylines')
        ->assertJsonPath('storylines.0.storyline_name', 'Main Plot')
        ->assertJsonPath('storylines.0.storyline_type', 'main')
        ->assertJsonPath('storylines.0.chapters.0.title', 'Alpha')
        ->assertJsonPath('storylines.1.storyline_name', 'Backstory')
        ->assertJsonPath('storylines.1.storyline_type', 'backstory')
        ->assertJsonPath('storylines.1.chapters.0.title', 'Beta');
});

// ─── Mixed File Types in Single Import ──────────────────────────────────

test('can import mixed file types together', function () {
    $book = Book::factory()->create();

    parseFiles($book, [
        [
            'file' => tempFile("Chapter 1: From TXT\n\nText content.", 'part1.txt'),
            'storyline_name' => 'Part 1',
            'storyline_type' => 'main',
        ],
        [
            'file' => tempFile("# From Markdown\n\nMarkdown content.", 'part2.md', 'text/markdown'),
            'storyline_name' => 'Part 2',
            'storyline_type' => 'parallel',
        ],
    ])->assertSuccessful()
        ->assertJsonCount(2, 'storylines')
        ->assertJsonPath('storylines.0.chapters.0.title', 'From TXT')
        ->assertJsonPath('storylines.1.chapters.0.title', 'From Markdown');
});

// ─── Merge Mode Edge Cases ──────────────────────────────────────────────

test('merge mode with single file preserves original data', function () {
    $book = Book::factory()->create();

    parseFiles($book, [
        [
            'file' => tempFile("Chapter 1: Solo\n\nAlone.", 'solo.txt'),
            'storyline_name' => 'Main',
            'storyline_type' => 'main',
        ],
    ], merge: true)
        ->assertSuccessful()
        ->assertJsonCount(1, 'storylines')
        ->assertJsonPath('storylines.0.storyline_name', 'Main')
        ->assertJsonPath('storylines.0.chapters.0.title', 'Solo');
});

test('merge mode renumbers chapters sequentially across files', function () {
    $book = Book::factory()->create();

    $response = parseFiles($book, [
        [
            'file' => tempFile("Chapter 1: A\n\nContent A.\n\nChapter 2: B\n\nContent B.", 'first.txt'),
            'storyline_name' => 'First',
            'storyline_type' => 'main',
        ],
        [
            'file' => tempFile("Chapter 1: C\n\nContent C.", 'second.txt'),
            'storyline_name' => 'Second',
            'storyline_type' => 'main',
        ],
    ], merge: true);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'storylines');

    $chapters = $response->json('storylines.0.chapters');
    expect(array_column($chapters, 'number'))->toBe([1, 2, 3])
        ->and(array_column($chapters, 'title'))->toBe(['A', 'B', 'C']);
});

// ─── Confirm Import Edge Cases ──────────────────────────────────────────

test('confirm import skips chapters with whitespace-only content', function () {
    $book = Book::factory()->create();

    $this->post(route('books.import.confirm', $book), [
        'storylines' => [[
            'name' => 'Main',
            'type' => 'main',
            'chapters' => [
                ['title' => 'Real', 'content' => 'Actual words.', 'word_count' => 2, 'included' => true],
                ['title' => 'Empty', 'content' => '   ', 'word_count' => 0, 'included' => true],
            ],
        ]],
    ])->assertRedirect();

    expect(Chapter::where('book_id', $book->id)->count())->toBe(1)
        ->and(Chapter::where('book_id', $book->id)->first()->title)->toBe('Real');
});

test('confirm import skips chapters with null content', function () {
    $book = Book::factory()->create();

    $this->post(route('books.import.confirm', $book), [
        'storylines' => [[
            'name' => 'Main',
            'type' => 'main',
            'chapters' => [
                ['title' => 'Has Content', 'content' => 'Words here.', 'word_count' => 2, 'included' => true],
                ['title' => 'Null Content', 'content' => null, 'word_count' => 0, 'included' => true],
            ],
        ]],
    ])->assertRedirect();

    expect(Chapter::where('book_id', $book->id)->count())->toBe(1);
});

test('confirm import with all chapters excluded still creates storyline', function () {
    $book = Book::factory()->create();

    $this->post(route('books.import.confirm', $book), [
        'storylines' => [[
            'name' => 'Empty Storyline',
            'type' => 'main',
            'chapters' => [
                ['title' => 'Skipped', 'content' => 'Content.', 'word_count' => 1, 'included' => false],
            ],
        ]],
    ])->assertRedirect();

    expect(Storyline::where('book_id', $book->id)->where('name', 'Empty Storyline')->exists())->toBeTrue()
        ->and(Chapter::where('book_id', $book->id)->count())->toBe(0);
});

test('confirm import with all empty content still creates storyline', function () {
    $book = Book::factory()->create();

    $this->post(route('books.import.confirm', $book), [
        'storylines' => [[
            'name' => 'No Words',
            'type' => 'main',
            'chapters' => [
                ['title' => 'Nothing', 'content' => '', 'word_count' => 0, 'included' => true],
            ],
        ]],
    ])->assertRedirect();

    expect(Storyline::where('book_id', $book->id)->where('name', 'No Words')->exists())->toBeTrue()
        ->and(Chapter::where('book_id', $book->id)->count())->toBe(0);
});

test('confirm import validation rejects empty storylines array', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.import.confirm', $book), [
        'storylines' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('storylines');
});

test('confirm import validation rejects empty chapters array', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.import.confirm', $book), [
        'storylines' => [[
            'name' => 'Main',
            'type' => 'main',
            'chapters' => [],
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('storylines.0.chapters');
});

test('confirm import validation rejects missing chapter title', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.import.confirm', $book), [
        'storylines' => [[
            'name' => 'Main',
            'type' => 'main',
            'chapters' => [
                ['content' => 'Some text.', 'word_count' => 2, 'included' => true],
            ],
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('storylines.0.chapters.0.title');
});

test('confirm import validation rejects chapter title over 255 chars', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.import.confirm', $book), [
        'storylines' => [[
            'name' => 'Main',
            'type' => 'main',
            'chapters' => [
                ['title' => str_repeat('A', 256), 'content' => 'Text.', 'word_count' => 1, 'included' => true],
            ],
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('storylines.0.chapters.0.title');
});

test('confirm import validation rejects negative word count', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.import.confirm', $book), [
        'storylines' => [[
            'name' => 'Main',
            'type' => 'main',
            'chapters' => [
                ['title' => 'Ch', 'content' => 'Text.', 'word_count' => -1, 'included' => true],
            ],
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('storylines.0.chapters.0.word_count');
});

test('confirm import validation rejects invalid storyline type', function () {
    $book = Book::factory()->create();

    $this->postJson(route('books.import.confirm', $book), [
        'storylines' => [[
            'name' => 'Main',
            'type' => 'invalid_type',
            'chapters' => [
                ['title' => 'Ch', 'content' => 'Text.', 'word_count' => 1, 'included' => true],
            ],
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('storylines.0.type');
});

// ─── DocumentParserFactory ──────────────────────────────────────────────

test('factory throws for unsupported extension', function () {
    $factory = new DocumentParserFactory;

    expect(fn () => $factory->forExtension('pdf'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported file extension: pdf');
});

test('factory is case-insensitive for extensions', function () {
    $factory = new DocumentParserFactory;

    expect($factory->forExtension('DOCX'))->toBeInstanceOf(DocxParserService::class)
        ->and($factory->forExtension('Md'))->toBeInstanceOf(MarkdownParserService::class)
        ->and($factory->forExtension('TXT'))->toBeInstanceOf(TxtParserService::class);
});

// ─── Markdown-Specific Edge Cases ───────────────────────────────────────

test('markdown with raw HTML is stripped for security', function () {
    $content = "# Chapter\n\n<script>alert('xss')</script>\n\nNormal text.";
    $file = tempFile($content, 'xss.md', 'text/markdown');

    $parser = new MarkdownParserService;
    $result = $parser->parse($file);

    expect($result['chapters'][0]['content'])
        ->not->toContain('<script>')
        ->toContain('Normal text');
});

// ─── TXT Parser Specific Edge Cases ─────────────────────────────────────

test('txt parser handles single paragraph without headings', function () {
    $file = tempFile('Just a single paragraph of text with no chapter headings at all.', 'single.txt');

    $parser = new TxtParserService;
    $result = $parser->parse($file);

    expect($result['chapters'])->toHaveCount(1)
        ->and($result['chapters'][0]['title'])->toBe('Full Document')
        ->and($result['chapters'][0]['word_count'])->toBeGreaterThan(0);
});

test('txt parser handles chapter heading without colon separator', function () {
    $content = "Chapter One\n\nFirst content.\n\nChapter Two\n\nSecond content.";
    $file = tempFile($content, 'no-colon.txt');

    $parser = new TxtParserService;
    $result = $parser->parse($file);

    expect($result['chapters'])->toHaveCount(2)
        ->and($result['chapters'][0]['title'])->toBe('Chapter One')
        ->and($result['chapters'][1]['title'])->toBe('Chapter Two');
});

// ─── Confirm Import Creates Correct Data Structure ──────────────────────

test('confirm import creates scenes for each chapter', function () {
    $book = Book::factory()->create();

    $this->post(route('books.import.confirm', $book), [
        'storylines' => [[
            'name' => 'Main',
            'type' => 'main',
            'chapters' => [
                ['title' => 'Ch 1', 'content' => 'First content.', 'word_count' => 2, 'included' => true],
                ['title' => 'Ch 2', 'content' => 'Second content.', 'word_count' => 2, 'included' => true],
            ],
        ]],
    ])->assertRedirect();

    $chapters = Chapter::where('book_id', $book->id)->with('scenes')->get();
    expect($chapters)->toHaveCount(2);

    foreach ($chapters as $chapter) {
        expect($chapter->scenes)->toHaveCount(1)
            ->and($chapter->scenes->first()->title)->toBe('Scene 1');
    }
});

test('confirm import assigns sequential reader_order with excluded gaps', function () {
    $book = Book::factory()->create();

    $this->post(route('books.import.confirm', $book), [
        'storylines' => [[
            'name' => 'Main',
            'type' => 'main',
            'chapters' => [
                ['title' => 'First', 'content' => 'A.', 'word_count' => 1, 'included' => true],
                ['title' => 'Skipped', 'content' => 'B.', 'word_count' => 1, 'included' => false],
                ['title' => 'Third', 'content' => 'C.', 'word_count' => 1, 'included' => true],
            ],
        ]],
    ])->assertRedirect();

    $chapters = Chapter::where('book_id', $book->id)->orderBy('reader_order')->get();
    expect($chapters)->toHaveCount(2)
        ->and($chapters[0]->reader_order)->toBe(0)
        ->and($chapters[0]->title)->toBe('First')
        ->and($chapters[1]->reader_order)->toBe(1)
        ->and($chapters[1]->title)->toBe('Third');
});

test('confirm import assigns sequential storyline sort_order', function () {
    $book = Book::factory()->create();

    $this->post(route('books.import.confirm', $book), [
        'storylines' => [
            ['name' => 'A', 'type' => 'main', 'chapters' => [
                ['title' => 'Ch', 'content' => 'X.', 'word_count' => 1, 'included' => true],
            ]],
            ['name' => 'B', 'type' => 'backstory', 'chapters' => [
                ['title' => 'Ch', 'content' => 'Y.', 'word_count' => 1, 'included' => true],
            ]],
            ['name' => 'C', 'type' => 'parallel', 'chapters' => [
                ['title' => 'Ch', 'content' => 'Z.', 'word_count' => 1, 'included' => true],
            ]],
        ],
    ])->assertRedirect();

    $storylines = Storyline::where('book_id', $book->id)->orderBy('sort_order')->get();
    expect($storylines)->toHaveCount(3)
        ->and($storylines->pluck('sort_order')->all())->toBe([0, 1, 2])
        ->and($storylines->pluck('name')->all())->toBe(['A', 'B', 'C']);
});
