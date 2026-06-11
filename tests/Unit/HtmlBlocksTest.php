<?php

use App\Support\HtmlBlocks;

test('split returns each top-level block element', function () {
    $blocks = HtmlBlocks::split('<p>One.</p><h2>Title</h2><blockquote><p>Quote.</p></blockquote><ul><li>a</li><li>b</li></ul>');

    expect($blocks)->toBe([
        '<p>One.</p>',
        '<h2>Title</h2>',
        '<blockquote><p>Quote.</p></blockquote>',
        '<ul><li>a</li><li>b</li></ul>',
    ]);
});

test('split preserves block HTML byte-identically including UTF-8 and entities', function () {
    $html = '<p>Müller — “quotes”… A &amp; B</p><p>after <strong>bold</strong> and <em>italics</em></p>';

    expect(implode('', HtmlBlocks::split($html)))->toBe($html);
});

test('split drops hr scene-break markers', function () {
    $blocks = HtmlBlocks::split('<p>One.</p><hr><p>Two.</p><hr/><p>Three.</p>');

    expect($blocks)->toBe(['<p>One.</p>', '<p>Two.</p>', '<p>Three.</p>']);
});

test('split keeps empty paragraphs so indices match the visible document', function () {
    expect(HtmlBlocks::split('<p>One.</p><p></p><p>Two.</p>'))
        ->toBe(['<p>One.</p>', '<p></p>', '<p>Two.</p>']);
});

test('split ignores whitespace-only text between blocks', function () {
    expect(HtmlBlocks::split("<p>One.</p>\n  <p>Two.</p>"))
        ->toBe(['<p>One.</p>', '<p>Two.</p>']);
});

test('split keeps stray top-level text as its own block', function () {
    expect(HtmlBlocks::split('<p>One.</p>loose text<p>Two.</p>'))
        ->toBe(['<p>One.</p>', 'loose text', '<p>Two.</p>']);
});

test('split returns an empty array for blank input', function () {
    expect(HtmlBlocks::split(null))->toBe([])
        ->and(HtmlBlocks::split(''))->toBe([])
        ->and(HtmlBlocks::split('   '))->toBe([]);
});
