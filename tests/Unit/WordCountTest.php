<?php

use App\Support\WordCount;

test('counts English words correctly', function () {
    expect(WordCount::count('Hello world'))->toBe(2);
});

test('counts Chinese characters as individual words', function () {
    expect(WordCount::count('你好世界'))->toBe(4);
});

test('counts Japanese mixed text with CJK and Latin', function () {
    // 5 CJK (東京タワー including prolonged sound mark) + 2 Latin (is, tall)
    expect(WordCount::count('東京タワー is tall'))->toBe(7);
});

test('counts Korean characters as individual words', function () {
    expect(WordCount::count('안녕하세요'))->toBe(5);
});

test('returns zero for empty string', function () {
    expect(WordCount::count(''))->toBe(0);
});

test('strips HTML tags before counting', function () {
    expect(WordCount::count('<p>Hello <strong>world</strong></p>'))->toBe(2);
});

test('counts mixed CJK and Latin text correctly', function () {
    // 7 CJK (日本語タイトル including katakana) + 2 Latin tokens (Chapter, 1:)
    expect(WordCount::count('Chapter 1: 日本語タイトル'))->toBe(9);
});

test('counts words with umlauts and accents as single words', function () {
    expect(WordCount::count('Müller ging über die Straße'))->toBe(5)
        ->and(WordCount::count('naïve café'))->toBe(2);
});

test('counts numbers as words', function () {
    expect(WordCount::count('It was 1999 and 42 things'))->toBe(6);
});

test('does not count standalone punctuation as words', function () {
    expect(WordCount::count('„Hallo“, sagte sie — leise.'))->toBe(4)
        ->and(WordCount::count('wait -- what?!'))->toBe(2);
});

test('keeps hyphenated and apostrophe words as single words', function () {
    expect(WordCount::count("don't re-do this"))->toBe(3);
});

test('treats adjacent block elements as word boundaries', function () {
    expect(WordCount::count('<p>End of one</p><p>Start of next</p>'))->toBe(6);
});

test('treats non-breaking spaces as word separators', function () {
    expect(WordCount::count('<p>one&nbsp;two</p>'))->toBe(2);
});

test('does not count decoded entity punctuation as words', function () {
    expect(WordCount::count('<p>fish &amp; chips</p>'))->toBe(2);
});

test('collapses arbitrary whitespace runs', function () {
    expect(WordCount::count("hello   world\n\nfoo"))->toBe(3);
});
