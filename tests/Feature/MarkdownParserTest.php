<?php

use App\Services\Parsers\MarkdownParserService;
use Illuminate\Http\UploadedFile;

function mdFixture(string $name): UploadedFile
{
    return new UploadedFile(
        path: __DIR__."/fixtures/{$name}",
        originalName: $name,
        mimeType: 'text/markdown',
        test: true,
    );
}

test('markdown parser splits on heading levels 1 and 2', function () {
    $parser = new MarkdownParserService;
    $result = $parser->parse(mdFixture('chapters.md'));

    expect($result['chapters'])->toHaveCount(3)
        ->and($result['chapters'][0]['title'])->toBe('The Morning After')
        ->and($result['chapters'][0]['number'])->toBe(1)
        ->and($result['chapters'][1]['title'])->toBe('Echoes')
        ->and($result['chapters'][1]['number'])->toBe(2)
        ->and($result['chapters'][2]['title'])->toBe('The Garden Wall')
        ->and($result['chapters'][2]['number'])->toBe(3);
});

test('markdown parser preserves bold and italic', function () {
    $parser = new MarkdownParserService;
    $result = $parser->parse(mdFixture('chapters.md'));
    $content = $result['chapters'][0]['content'];

    expect($content)
        ->toContain('<strong>Bold text</strong>')
        ->toContain('<em>italic text</em>');
});

test('markdown parser converts horizontal rules to scene breaks', function () {
    $parser = new MarkdownParserService;
    $result = $parser->parse(mdFixture('chapters.md'));
    $content = $result['chapters'][0]['content'];

    expect($content)->toMatch('/<hr\s*\/?>/')
        ->and($content)->toContain('After the scene break');
});

test('markdown parser keeps subheadings as content', function () {
    $parser = new MarkdownParserService;
    $result = $parser->parse(mdFixture('chapters.md'));

    // The ### subheading should NOT create a fourth chapter
    expect($result['chapters'])->toHaveCount(3);

    // It should appear as <h3> inside the Echoes chapter
    $echoesContent = $result['chapters'][1]['content'];
    expect($echoesContent)->toContain('<h3>');
});

test('markdown parser falls back for headingless files', function () {
    $parser = new MarkdownParserService;
    $result = $parser->parse(mdFixture('no-headings.md'));

    expect($result['chapters'])->toHaveCount(1)
        ->and($result['chapters'][0]['title'])->toBe('Full Document')
        ->and($result['chapters'][0]['number'])->toBe(1)
        ->and($result['chapters'][0]['word_count'])->toBeGreaterThan(0);
});

test('markdown parser preserves blockquotes', function () {
    $parser = new MarkdownParserService;
    $result = $parser->parse(mdFixture('chapters.md'));
    $echoesContent = $result['chapters'][1]['content'];

    expect($echoesContent)->toContain('<blockquote>');
});
