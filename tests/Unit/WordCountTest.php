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
    // 7 CJK (日本語タイトル including katakana) + 1 Latin (Chapter; numbers excluded by str_word_count)
    expect(WordCount::count('Chapter 1: 日本語タイトル'))->toBe(8);
});
