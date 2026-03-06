<?php

namespace App\Ai\Middleware;

use App\Models\AiSetting;
use App\Models\Book;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

class InjectProviderCredentials
{
    public function __construct(protected Book $book) {}

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $setting = AiSetting::forProvider($this->book->ai_provider);

        $setting->injectConfig();

        return $next($prompt);
    }
}
