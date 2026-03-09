<?php

namespace App\Services;

class AiUsageService
{
    /**
     * Calculate cost in microdollars for a text generation call.
     */
    public function calculateCost(int $inputTokens, int $outputTokens, string $model): int
    {
        $pricing = config("ai-costs.models.{$model}", config('ai-costs.default'));

        $inputCost = (int) round($inputTokens * $pricing['input'] / 1_000_000);
        $outputCost = (int) round($outputTokens * $pricing['output'] / 1_000_000);

        return $inputCost + $outputCost;
    }

    /**
     * Calculate cost in microdollars for an embedding call.
     */
    public function calculateEmbeddingCost(int $tokens, string $model): int
    {
        $pricePerMillion = config("ai-costs.embedding_models.{$model}", config('ai-costs.embedding_default'));

        return (int) round($tokens * $pricePerMillion / 1_000_000);
    }
}
