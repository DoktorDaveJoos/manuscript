<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Responses\AgentResponse;

use function Laravel\Ai\agent;

class WritingStyleService
{
    /**
     * Extract writing style from sample chapter content.
     *
     * @return array<string, mixed>
     */
    public function extract(string $sampleText, Book $book): array
    {
        $setting = AiSetting::forProvider($book->ai_provider);
        $setting->injectConfig();

        $langName = $book->language === 'de' ? 'German' : 'English';
        $provider = $book->ai_provider->toLab()->value;

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
                'distinctive_features' => $schema->array()->required(),
            ],
        )->prompt(
            "Analyze this {$langName} manuscript excerpt and extract the writing style:\n\n{$sampleText}",
            provider: $provider,
        );

        return $response->toArray();
    }
}
