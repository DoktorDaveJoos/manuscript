<?php

namespace App\Services\Normalization\Rules;

use App\Services\Normalization\NormalizationRule;

class DialogueRule implements NormalizationRule
{
    public function name(): string
    {
        return 'Dialogue';
    }

    /**
     * @return array{content: string, changes: int}
     */
    public function apply(string $html, string $language): array
    {
        $changes = 0;

        // Split into paragraphs, then check for multiple dialogue lines in a single paragraph
        $quotePattern = $language === 'de'
            ? "[\x{201E}\x{00AB}]"  // German opening quotes: „ or «
            : "[\x{201C}]";         // English opening quote: "

        // Find paragraphs that contain multiple dialogue lines
        $result = preg_replace_callback(
            '#<p>(.*?)</p>#su',
            function ($matches) use (&$changes, $quotePattern) {
                $content = $matches[1];

                // Check if there are multiple dialogue openings in one paragraph
                if (preg_match_all('/'.$quotePattern.'/u', $content) > 1) {
                    // Split before each dialogue opening (except the first)
                    $parts = preg_split(
                        '/(?='.$quotePattern.')/u',
                        $content,
                        -1,
                        PREG_SPLIT_NO_EMPTY
                    );

                    if (count($parts) > 1) {
                        $changes += count($parts) - 1;
                        $paragraphs = array_map(fn ($p) => '<p>'.trim($p).'</p>', $parts);

                        return implode('', $paragraphs);
                    }
                }

                return $matches[0];
            },
            $html
        );

        return ['content' => $result, 'changes' => $changes];
    }
}
