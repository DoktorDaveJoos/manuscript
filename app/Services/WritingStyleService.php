<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Book;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Ai;
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
        $providerName = $setting->provider->toLab()->value;
        // Style extraction is mechanical pattern-spotting — pick the cheapest
        // text model the active provider exposes. Tracks SDK defaults, so when
        // a new "cheap" model lands (Haiku 4.7, gpt-5-nano, …) we get it for
        // free on next composer update.
        $model = Ai::textProvider($providerName)->cheapestTextModel();

        /** @var AgentResponse $response */
        $response = agent(
            instructions: <<<INSTRUCTIONS
            You are a ghostwriter studying an author's prose voice. Your job is to identify concrete,
            reproducible patterns — not academic labels. For every field, describe the pattern specifically
            enough that another writer could imitate it. Quote short phrases from the text as evidence.
            Respond in {$langName}.
            INSTRUCTIONS,
            schema: fn (JsonSchema $schema) => [
                'narrative_voice' => $schema->string()
                    ->description('POV (first/third/etc.), narrator distance (close, distant, shifting), and how information is revealed to the reader.')
                    ->required(),
                'tense' => $schema->string()
                    ->description('Primary tense and any tense-shifting patterns (e.g. past narrative with present-tense flashbacks).')
                    ->required(),
                'tone' => $schema->string()
                    ->description('Emotional register and attitude — e.g. wry, melancholic, detached, urgent. Note shifts between scenes.')
                    ->required(),
                'sentence_rhythm' => $schema->string()
                    ->description('Length patterns, cadence, use of fragments or run-ons, and any signature sentence structures.')
                    ->required(),
                'paragraph_style' => $schema->string()
                    ->description('Typical paragraph length, density, transitions between paragraphs, and use of white space or single-line paragraphs.')
                    ->required(),
                'vocabulary' => $schema->string()
                    ->description('Register (formal/colloquial), concreteness vs. abstraction, sensory preferences, and any domain-specific language.')
                    ->required(),
                'figurative_language' => $schema->string()
                    ->description('Use of metaphor, simile, personification — frequency, style, and whether figurative language is ornamental or structural.')
                    ->required(),
                'pacing' => $schema->string()
                    ->description('Scene-to-summary ratio, time compression techniques, chapter/section rhythm.')
                    ->required(),
                'distinctive_features' => $schema->array()->items($schema->string())
                    ->description('Unique author fingerprints — recurring motifs, tics, structural habits, or stylistic choices that set this voice apart.')
                    ->required(),
            ],
        )->prompt(
            "Study this {$langName} manuscript excerpt and extract the prose style. For each field, describe the pattern concretely enough that another writer could reproduce it.\n\n{$sampleText}",
            provider: $providerName,
            model: $model,
            timeout: 150,
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
        $book->recordAiUsage($usage->promptTokens, $usage->completionTokens, $cost, 'writing_style', $model);

        return $response->toArray();
    }
}
