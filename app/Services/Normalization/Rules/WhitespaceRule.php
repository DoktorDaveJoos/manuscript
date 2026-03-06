<?php

namespace App\Services\Normalization\Rules;

use App\Services\Normalization\NormalizationRule;

class WhitespaceRule implements NormalizationRule
{
    public function name(): string
    {
        return 'Whitespace';
    }

    /**
     * @return array{content: string, changes: int}
     */
    public function apply(string $html, string $language): array
    {
        $changes = 0;

        // Collapse multiple spaces into one (outside of tags)
        $result = preg_replace_callback(
            '/(?<=>)([^<]+)(?=<)/',
            function ($matches) use (&$changes) {
                $text = $matches[1];
                $cleaned = preg_replace('/ {2,}/', ' ', $text, -1, $count);
                $changes += $count;

                return $cleaned;
            },
            $html
        );

        // Remove trailing spaces before closing tags
        $result = preg_replace_callback(
            '/ +(<\/[^>]+>)/',
            function ($matches) use (&$changes) {
                $changes++;

                return $matches[1];
            },
            $result
        );

        // Remove leading spaces after opening tags
        $result = preg_replace_callback(
            '/(<[^\/][^>]*>) +/',
            function ($matches) use (&$changes) {
                $changes++;

                return $matches[1];
            },
            $result
        );

        return ['content' => $result, 'changes' => $changes];
    }
}
