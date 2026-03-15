<?php

namespace App\Listeners;

use App\Ai\Contracts\BelongsToBook;
use App\Services\AiUsageService;
use Laravel\Ai\Events\AgentPrompted;

class RecordAiTokenUsage
{
    public function __construct(protected AiUsageService $usageService) {}

    public function handle(AgentPrompted $event): void
    {
        $agent = $event->prompt->agent;

        if (! $agent instanceof BelongsToBook) {
            return;
        }

        $usage = $event->response->usage;
        $model = $event->response->meta->model ?? 'unknown';

        $inputTokens = $usage->promptTokens;
        $outputTokens = $usage->completionTokens;
        $cost = $this->usageService->calculateCost(
            $inputTokens,
            $outputTokens,
            $model,
            $usage->cacheReadInputTokens,
            $usage->cacheWriteInputTokens,
            $event->response->meta->provider,
        );

        $feature = $this->resolveFeature($agent);

        $agent->book()->recordAiUsage($inputTokens, $outputTokens, $cost, $feature, $model);
    }

    private function resolveFeature(BelongsToBook $agent): string
    {
        return match (class_basename($agent)) {
            'BookChatAgent' => 'chat',
            'ChapterAnalyzer', 'ManuscriptAnalyzer' => 'analysis',
            'ProseReviser', 'NextChapterAdvisor' => 'suggestions',
            'TextBeautifier' => 'beautify',
            'StoryBibleBuilder' => 'story_bible',
            'EntityExtractor', 'EntityConsolidator' => 'entity_extraction',
            default => 'other',
        };
    }
}
