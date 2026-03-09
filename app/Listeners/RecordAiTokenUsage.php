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
        $cost = $this->usageService->calculateCost($inputTokens, $outputTokens, $model);

        $agent->book()->recordAiUsage($inputTokens, $outputTokens, $cost);
    }
}
