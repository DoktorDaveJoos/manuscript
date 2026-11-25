<?php

namespace App\Ai\Concerns;

trait ProviderSafeTemperature
{
    abstract protected function defaultTemperature(): float;

    /**
     * Skip temperature when the active provider is OpenAI. The gpt-5 family
     * rejects the `temperature` parameter outright ("Unsupported parameter:
     * 'temperature' is not supported with this model"). Wired via method
     * instead of a `#[Temperature]` attribute because Laravel AI's
     * TextGenerationOptions::resolve() falls back to the attribute whenever
     * temperature() returns null.
     */
    public function temperature(): ?float
    {
        if (config('ai.default') === 'openai') {
            return null;
        }

        return $this->defaultTemperature();
    }
}
