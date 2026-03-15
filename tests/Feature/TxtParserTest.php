<?php

use App\Services\Parsers\TxtParserService;
use Illuminate\Http\UploadedFile;

function txtFixture(string $name): UploadedFile
{
    return new UploadedFile(
        path: __DIR__."/fixtures/{$name}",
        originalName: $name,
        mimeType: 'text/plain',
        test: true,
    );
}

test('txt parser splits on chapter patterns', function () {
    $parser = new TxtParserService;
    $result = $parser->parse(txtFixture('chapters.txt'));

    expect($result['chapters'])->toHaveCount(3)
        ->and($result['chapters'][0]['number'])->toBe(1)
        ->and($result['chapters'][0]['title'])->toBe('The Morning After')
        ->and($result['chapters'][1]['number'])->toBe(2)
        ->and($result['chapters'][1]['title'])->toBe('Echoes')
        ->and($result['chapters'][2]['number'])->toBe(3)
        ->and($result['chapters'][2]['title'])->toBe('The Garden Wall');
});

test('txt parser falls back to single chapter without headings', function () {
    $parser = new TxtParserService;
    $result = $parser->parse(txtFixture('no-headings.txt'));

    expect($result['chapters'])->toHaveCount(1)
        ->and($result['chapters'][0]['title'])->toBe('Full Document')
        ->and($result['chapters'][0]['number'])->toBe(1)
        ->and($result['chapters'][0]['word_count'])->toBeGreaterThan(0);
});

test('txt parser wraps paragraphs in p tags', function () {
    $parser = new TxtParserService;
    $result = $parser->parse(txtFixture('chapters.txt'));

    expect($result['chapters'][0]['content'])
        ->toContain('<p>')
        ->toContain('</p>');
});

test('txt parser converts scene breaks to hr', function () {
    $path = tempnam(sys_get_temp_dir(), 'txt_');
    file_put_contents($path, "Chapter 1: Before\n\nSome text here.\n\n***\n\nMore text after the break.");

    $file = new UploadedFile($path, 'scene-break.txt', 'text/plain', null, true);
    $parser = new TxtParserService;
    $result = $parser->parse($file);

    expect($result['chapters'][0]['content'])->toContain('<hr>');

    @unlink($path);
});

test('txt parser escapes HTML entities', function () {
    $path = tempnam(sys_get_temp_dir(), 'txt_');
    file_put_contents($path, "Chapter 1: Safety First\n\nTom & Jerry went to <script>alert('xss')</script> the park.");

    $file = new UploadedFile($path, 'entities.txt', 'text/plain', null, true);
    $parser = new TxtParserService;
    $result = $parser->parse($file);

    expect($result['chapters'][0]['content'])
        ->toContain('Tom &amp; Jerry')
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>');

    @unlink($path);
});
