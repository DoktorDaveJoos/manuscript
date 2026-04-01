<?php

namespace App\Services\Normalization\Rules;

use App\Services\Normalization\NormalizationRule;
use Normalizer;

class UnicodeNormalizationRule implements NormalizationRule
{
    public function name(): string
    {
        return 'Unicode NFC';
    }

    /**
     * @return array{content: string, changes: int}
     */
    public function apply(string $html, string $language): array
    {
        $normalized = Normalizer::normalize($html, Normalizer::FORM_C);

        if ($normalized === false || $normalized === $html) {
            return ['content' => $html, 'changes' => 0];
        }

        return ['content' => $normalized, 'changes' => 1];
    }
}
