<?php

namespace App\Services\Normalization\Rules;

use App\Services\Normalization\NormalizationRule;

class EllipsisRule implements NormalizationRule
{
    public function name(): string
    {
        return 'Ellipsis';
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
            function ($matches) use (&$changes) {
                $text = $matches[0];

                // Replace three dots with ellipsis character
                $text = preg_replace_callback(
                    '/\.{3}/',
                    function () use (&$changes) {
                        $changes++;

                        return "\u{2026}";
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
