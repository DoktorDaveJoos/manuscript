<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Responses\AgentResponse;

use function Laravel\Ai\agent;

class WritingStyleService
{
    public function __construct(private AiUsageService $usageService) {}

    /**
     * Extract writing style from sample chapter content.
     *
     * @return array<string, mixed>
     */
    public function extract(string $sampleText, Book $book): array
    {
        $setting = AiSetting::activeProvider();
        abort_if(! $setting, 422, 'No AI provider configured.');

        $setting->injectConfig();

        $langName = $book->language === 'de' ? 'German' : 'English';
        $provider = $setting->provider->toLab()->value;

        /** @var AgentResponse $response */
        $response = agent(
            instructions: "You are a literary style analyst. Analyze {$langName} manuscript excerpts and extract the author's writing style into structured data.",
            schema: fn (JsonSchema $schema) => [
                'tone' => $schema->string()->required(),
                'pov' => $schema->string()->required(),
                'tense' => $schema->string()->required(),
                'sentence_style' => $schema->string()->required(),
                'vocabulary_level' => $schema->string()->required(),
                'dialogue_style' => $schema->string()->required(),
                'imagery' => $schema->string()->required(),
                'pacing' => $schema->string()->required(),
                'distinctive_features' => $schema->array()->items($schema->string())->required(),
            ],
        )->prompt(
            "Analyze this {$langName} manuscript excerpt and extract the writing style:\n\n{$sampleText}",
            provider: $provider,
        );

        $usage = $response->usage;
        $model = $response->meta->model ?? 'unknown';
        $cost = $this->usageService->calculateCost(
            $usage->promptTokens,
            $usage->completionTokens,
            $model,
            $usage->cacheReadInputTokens,
            $usage->cacheWriteInputTokens,
            $response->meta->provider,
        );
        $book->recordAiUsage($usage->promptTokens, $usage->completionTokens, $cost);

        return $response->toArray();
    }
}
