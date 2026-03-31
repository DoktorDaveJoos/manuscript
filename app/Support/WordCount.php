<?php

namespace App\Support;

class WordCount
{
    private const CJK_PATTERN = '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u';

    /**
     * Count words in text, with support for CJK characters.
     *
     * CJK characters are counted individually (each character = one word).
     * Latin/Cyrillic text uses standard space-separated word counting.
     */
    public static function count(string $text): int
    {
        $text = strip_tags($text);

        $cjkCount = preg_match_all(self::CJK_PATTERN, $text);

        $nonCjk = preg_replace(self::CJK_PATTERN, ' ', $text);
        $latinCount = str_word_count(trim($nonCjk));

        return $cjkCount + $latinCount;
    }
}
