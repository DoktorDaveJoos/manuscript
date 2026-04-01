<?php

use App\Services\Parsers\EpubParserService;
use Illuminate\Http\UploadedFile;

function epubFixture(string $name): UploadedFile
{
    return new UploadedFile(
        path: __DIR__."/fixtures/{$name}",
        originalName: $name,
        mimeType: 'application/epub+zip',
        test: true,
    );
}

test('epub parser extracts chapters from headings', function () {
    $parser = new EpubParserService;
    $result = $parser->parse(epubFixture('chapters.epub'));

    expect($result['chapters'])->toHaveCount(3)
        ->and($result['chapters'][0]['number'])->toBe(1)
        ->and($result['chapters'][0]['title'])->toBe('The Morning After')
        ->and($result['chapters'][0]['content'])->toContain('The sun rose slowly over the valley.')
        ->and($result['chapters'][1]['number'])->toBe(2)
        ->and($result['chapters'][1]['title'])->toBe('Echoes')
        ->and($result['chapters'][1]['content'])->toContain('The hallway stretched endlessly before her.')
        ->and($result['chapters'][2]['number'])->toBe(3)
        ->and($result['chapters'][2]['title'])->toBe('The Garden Wall')
        ->and($result['chapters'][2]['content'])->toContain('Ivy crept along the old stones.');
});

test('epub parser falls back to single chapter without headings', function () {
    $parser = new EpubParserService;
    $result = $parser->parse(epubFixture('no-headings.epub'));

    expect($result['chapters'])->toHaveCount(1)
        ->and($result['chapters'][0]['title'])->toBe('Full Document')
        ->and($result['chapters'][0]['number'])->toBe(1)
        ->and($result['chapters'][0]['content'])->toContain('The morning was cold and still.')
        ->and($result['chapters'][0]['content'])->toContain('She walked down the path.');
});

test('epub parser preserves inline formatting', function () {
    $parser = new EpubParserService;
    $result = $parser->parse(epubFixture('formatted.epub'));
    $content = $result['chapters'][0]['content'];

    expect($content)
        ->toContain('<strong>bold text</strong>')
        ->toContain('<em>italic text</em>')
        ->toContain('<u>underlined</u>');
});

test('epub parser converts scene breaks to hr', function () {
    $parser = new EpubParserService;
    $result = $parser->parse(epubFixture('formatted.epub'));
    $content = $result['chapters'][0]['content'];

    expect($content)->toContain('<hr>');
});

test('epub parser converts blockquote elements', function () {
    $parser = new EpubParserService;
    $result = $parser->parse(epubFixture('formatted.epub'));
    $content = $result['chapters'][0]['content'];

    expect($content)->toContain('<blockquote><p>This is a blockquote paragraph.</p></blockquote>');
});

test('epub parser escapes special characters', function () {
    $parser = new EpubParserService;
    $result = $parser->parse(epubFixture('formatted.epub'));
    $content = $result['chapters'][0]['content'];

    expect($content)
        ->toContain('Tom &amp; Jerry')
        ->toContain('&lt;script&gt;');
});
