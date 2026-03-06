<?php

namespace App\Services\Normalization\Rules;

use App\Services\Normalization\NormalizationRule;

class DashRule implements NormalizationRule
{
    public function name(): string
    {
        return 'Dashes';
    }

    /**
     * @return array{content: string, changes: int}
     */
    public function apply(string $html, string $language): array
    {
        $changes = 0;

        // Process text nodes only
        $result = preg_replace_callback(
            '/(?<=>)([^<]+)(?=<)|^([^<]+)/',
            function ($matches) use (&$changes, $language) {
                $text = $matches[0];

                // Replace double/triple hyphens with em-dash
                $text = preg_replace_callback(
                    '/(?<=\s)---?(?=\s)/',
                    function () use (&$changes) {
                        $changes++;

                        return "\u{2014}";
                    },
                    $text
                );

                // For German: em-dash for dialogue (Gedankenstrich), with spaces
                // For English: em-dash without spaces
                if ($language === 'en') {
                    // Normalize spaced em-dashes to unspaced in English
                    $text = preg_replace_callback(
                        '/\s\x{2014}\s/u',
                        function () use (&$changes) {
                            $changes++;

                            return "\u{2014}";
                        },
                        $text
                    );
                }

                // Replace hyphen between numbers with en-dash
                $text = preg_replace_callback(
                    '/(\d)\s*-\s*(\d)/',
                    function ($m) use (&$changes) {
                        $changes++;

                        return $m[1]."\u{2013}".$m[2];
                    },
                    $text
                );

                return $text;
            },
            $html
        );

        return ['content' => $result, 'changes' => $changes];
    }
}
