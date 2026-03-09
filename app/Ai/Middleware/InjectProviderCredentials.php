<?php

namespace App\Ai\Middleware;

use App\Models\AiSetting;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class InjectProviderCredentials
{
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $setting = AiSetting::activeProvider();

        $setting?->injectConfig();

        return $next($prompt);
    }
}
