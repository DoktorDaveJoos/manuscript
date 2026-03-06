<?php

namespace App\Services\Normalization\Rules;

use App\Services\Normalization\NormalizationRule;

class ParagraphRule implements NormalizationRule
{
    public function name(): string
    {
        return 'Paragraphs';
    }

    /**
     * @return array{content: string, changes: int}
     */
    public function apply(string $html, string $language): array
    {
        $changes = 0;

        // Collapse double <br> into paragraph breaks
        $result = preg_replace_callback(
            '#(<br\s*/?>)\s*(<br\s*/?>)#i',
            function () use (&$changes) {
                $changes++;

                return '</p><p>';
            },
            $html
        );

        // Remove empty paragraphs
        $result = preg_replace_callback(
            '#<p>\s*</p>#i',
            function () use (&$changes) {
                $changes++;

                return '';
            },
            $result
        );

        // Remove leading/trailing <br> inside paragraphs
        $result = preg_replace_callback(
            '#<p>\s*<br\s*/?>\s*#i',
            function () use (&$changes) {
                $changes++;

                return '<p>';
            },
            $result
        );

        $result = preg_replace_callback(
            '#\s*<br\s*/?>\s*</p>#i',
            function () use (&$changes) {
                $changes++;

                return '</p>';
            },
            $result
        );

        return ['content' => $result, 'changes' => $changes];
    }
}
