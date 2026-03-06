<?php

namespace App\Services\Normalization\Rules;

use App\Services\Normalization\NormalizationRule;

class SmartQuoteRule implements NormalizationRule
{
    public function name(): string
    {
        return 'Smart quotes';
    }

    /**
     * @return array{content: string, changes: int}
     */
    public function apply(string $html, string $language): array
    {
        $changes = 0;

        // Process text nodes only (content between tags)
        $result = preg_replace_callback(
            '/(?<=>)([^<]+)(?=<)|^([^<]+)/',
            function ($matches) use (&$changes, $language) {
                $text = $matches[0];

                return $this->convertQuotes($text, $language, $changes);
            },
            $html
        );

        return ['content' => $result, 'changes' => $changes];
    }

    private function convertQuotes(string $text, string $language, int &$changes): string
    {
        if ($language === 'de') {
            return $this->convertGermanQuotes($text, $changes);
        }

        return $this->convertEnglishQuotes($text, $changes);
    }

    private function convertGermanQuotes(string $text, int &$changes): string
    {
        // Replace straight double quotes with German typographic quotes „ "
        $result = preg_replace_callback(
            '/"([^"]+)"/',
            function ($matches) use (&$changes) {
                $changes++;

                return "\u{201E}".$matches[1]."\u{201C}";
            },
            $text
        );

        // Replace straight single quotes with German typographic quotes ‚ '
        $result = preg_replace_callback(
            "/(?<=\s|^)'([^']+)'/",
            function ($matches) use (&$changes) {
                $changes++;

                return "\u{201A}".$matches[1]."\u{2018}";
            },
            $result
        );

        return $result;
    }

    private function convertEnglishQuotes(string $text, int &$changes): string
    {
        // Replace straight double quotes with English typographic quotes " "
        $result = preg_replace_callback(
            '/"([^"]+)"/',
            function ($matches) use (&$changes) {
                $changes++;

                return "\u{201C}".$matches[1]."\u{201D}";
            },
            $text
        );

        // Replace straight single quotes with English typographic quotes ' '
        $result = preg_replace_callback(
            "/(?<=\s|^)'([^']+)'/",
            function ($matches) use (&$changes) {
                $changes++;

                return "\u{2018}".$matches[1]."\u{2019}";
            },
            $result
        );

        return $result;
    }
}
