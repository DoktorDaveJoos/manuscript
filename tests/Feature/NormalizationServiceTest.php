<?php

use App\Services\Normalization\NormalizationService;

beforeEach(function () {
    $this->service = new NormalizationService;
});

test('collapses multiple spaces into one', function () {
    $result = $this->service->normalize('<p>Hello    world</p>', 'en');

    expect($result['content'])->toBe('<p>Hello world</p>');
    expect($result['total_changes'])->toBeGreaterThan(0);
});

test('removes trailing spaces before closing tags', function () {
    $result = $this->service->normalize('<p>Hello   </p>', 'en');

    expect($result['content'])->toBe('<p>Hello</p>');
    expect($result['total_changes'])->toBeGreaterThan(0);
});

test('removes leading spaces after opening tags', function () {
    $result = $this->service->normalize('<p>   Hello</p>', 'en');

    expect($result['content'])->toBe('<p>Hello</p>');
    expect($result['total_changes'])->toBeGreaterThan(0);
});

test('collapses double br into paragraph breaks', function () {
    $result = $this->service->normalize('<p>Hello<br><br>World</p>', 'en');

    expect($result['content'])->toBe('<p>Hello</p><p>World</p>');
});

test('removes empty paragraphs', function () {
    $result = $this->service->normalize('<p>Hello</p><p>  </p><p>World</p>', 'en');

    expect($result['content'])->toBe('<p>Hello</p><p>World</p>');
});

test('replaces three dots with ellipsis character', function () {
    $result = $this->service->normalize('<p>Wait...</p>', 'en');

    expect($result['content'])->toBe("<p>Wait\u{2026}</p>");
});

test('replaces double hyphens with em-dash', function () {
    $result = $this->service->normalize('<p>She said -- yes</p>', 'en');

    expect($result['content'])->toContain("\u{2014}");
});

test('normalizes spaced em-dashes in english to unspaced', function () {
    $result = $this->service->normalize("<p>word \u{2014} word</p>", 'en');

    expect($result['content'])->toBe("<p>word\u{2014}word</p>");
});

test('keeps spaced em-dashes in german', function () {
    $result = $this->service->normalize("<p>Wort \u{2014} Wort</p>", 'de');

    expect($result['content'])->toContain(" \u{2014} ");
    expect($result['total_changes'])->toBe(0);
});

test('replaces hyphens between numbers with en-dash', function () {
    $result = $this->service->normalize('<p>pages 1-10</p>', 'en');

    expect($result['content'])->toContain("1\u{2013}10");
});

test('converts straight double quotes to english smart quotes', function () {
    $result = $this->service->normalize('<p>"Hello," she said.</p>', 'en');

    expect($result['content'])->toContain("\u{201C}Hello,\u{201D}");
});

test('converts straight double quotes to german typographic quotes', function () {
    $result = $this->service->normalize('<p>"Hallo," sagte sie.</p>', 'de');

    expect($result['content'])->toContain("\u{201E}Hallo,\u{201C}");
});

test('converts straight single quotes to english smart quotes', function () {
    $result = $this->service->normalize("<p>She said 'yes' today.</p>", 'en');

    expect($result['content'])->toContain("\u{2018}yes\u{2019}");
});

test('splits multiple dialogue lines into separate paragraphs for english', function () {
    $input = "<p>\u{201C}Hello!\u{201D} she said. \u{201C}How are you?\u{201D}</p>";
    $result = $this->service->normalize($input, 'en');

    expect($result['content'])->toContain('</p><p>');
});

test('returns zero changes for already clean content', function () {
    $result = $this->service->normalize('<p>Clean text here.</p>', 'en');

    expect($result['total_changes'])->toBe(0);
    expect($result['changes'])->toBeEmpty();
});

test('returns correct change structure', function () {
    $result = $this->service->normalize('<p>Hello    world...</p>', 'en');

    expect($result)->toHaveKeys(['content', 'changes', 'total_changes']);
    expect($result['changes'])->toBeArray();

    foreach ($result['changes'] as $change) {
        expect($change)->toHaveKeys(['rule', 'count']);
        expect($change['count'])->toBeGreaterThan(0);
    }
});
