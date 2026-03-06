<?php

namespace App\Services\Normalization;

interface NormalizationRule
{
    public function name(): string;

    /**
     * @return array{content: string, changes: int}
     */
    public function apply(string $html, string $language): array;
}
