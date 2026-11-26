<?php

namespace App\Services;

class AiUsageService
{
    /**
     * Calculate cost in microdollars for a text generation call.
     */
    public function calculateCost(
        int $inputTokens,
        int $outputTokens,
        string $model,
        int $cacheReadTokens = 0,
        int $cacheWriteTokens = 0,
        ?string $provider = null,
    ): int {
        $models = config('ai-costs.models', []);
        $normalizedModel = $this->normalizeModelName($model, $models);
        $pricing = $models[$normalizedModel] ?? config('ai-costs.default');

        $inputRate = $pricing['input'] / 1_000_000;
        // Laravel AI reports uncached input, cache read, and cache write tokens
        // as disjoint buckets across both Anthropic and OpenAI gateways.
        $inputCost = $inputTokens * $inputRate;
        $outputCost = $outputTokens * $pricing['output'] / 1_000_000;

        $cacheReadCost = 0.0;
        $cacheWriteCost = 0.0;

        if ($cacheReadTokens > 0) {
            $cacheReadMultiplier = match ($provider) {
                'anthropic' => 0.10,
                'openai' => 0.50,
                default => 0.50,
            };

            $cacheReadCost = $cacheReadTokens * $inputRate * $cacheReadMultiplier;
        }

        if ($cacheWriteTokens > 0 && $provider === 'anthropic') {
            $cacheWriteCost = $cacheWriteTokens * $inputRate * 1.25;
        }

        return max(0, (int) round($inputCost + $cacheReadCost + $cacheWriteCost + $outputCost));
    }

    /**
     * Calculate cost in microdollars for an embedding call.
     */
    public function calculateEmbeddingCost(int $tokens, string $model): int
    {
        $models = config('ai-costs.embedding_models', []);
        $normalizedModel = $this->normalizeModelName($model, $models);
        $pricePerMillion = $models[$normalizedModel] ?? config('ai-costs.embedding_default');

        return max(0, (int) round($tokens * $pricePerMillion / 1_000_000));
    }

    /**
     * Normalize a model name by stripping date/preview suffixes.
     *
     * Tries exact match first, then strips trailing `-YYYY-MM-DD`, `-YYYYMMDD`,
     * and `-preview-MM-DD` suffixes to find a config entry.
     *
     * @param  array<string, array{input: int, output: int}>  $models
     */
    public function normalizeModelName(string $model, array $models = []): string
    {
        if (! $models) {
            $models = config('ai-costs.models', []);
        }

        if (isset($models[$model])) {
            return $model;
        }

        $stripped = preg_replace('/-(?:\d{4}-\d{2}-\d{2}|\d{8})$/', '', $model);

        if ($stripped !== $model && isset($models[$stripped])) {
            return $stripped;
        }

        $stripped = preg_replace('/-preview-\d{2}-\d{2}$/', '', $model);

        if ($stripped !== $model && isset($models[$stripped])) {
            return $stripped;
        }

        return $model;
    }
}
