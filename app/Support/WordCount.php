<?php

namespace App\Support;

class WordCount
{
    private const CJK_PATTERN = '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u';

    /**
     * Count words the way word processors (Google Docs, Word) do.
     *
     * HTML tags act as word boundaries and entities are decoded first.
     * Text is split on Unicode whitespace; a token counts as a word only
     * if it contains at least one letter or number, so standalone
     * punctuation ("—", "--") is ignored while numbers ("1999") count.
     * CJK characters are counted individually (each character = one word).
     *
     * The TypeScript twin of this algorithm lives in
     * resources/js/lib/wordCount.ts — keep both in sync.
     */
    public static function count(string $text): int
    {
        if (trim($text) === '') {
            return 0;
        }

        $text = html_entity_decode(
            preg_replace('/<[^>]*>/', ' ', $text),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );

        $cjkCount = preg_match_all(self::CJK_PATTERN, $text);

        $nonCjk = preg_replace(self::CJK_PATTERN, ' ', $text);
        $tokens = preg_split('/[\s\p{Z}]+/u', $nonCjk, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $latinCount = count(array_filter(
            $tokens,
            fn (string $token): bool => preg_match('/[\p{L}\p{N}]/u', $token) === 1,
        ));

        return $cjkCount + $latinCount;
    }
}
