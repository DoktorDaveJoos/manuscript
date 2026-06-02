<?php

namespace App\Ai\Concerns;

use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;

/**
 * Adds Anthropic prompt-caching to an agent's system prompt.
 *
 * Anthropic does not auto-cache the system block, so we split `instructions()`
 * on a sentinel: everything before {@see self::CACHE_BREAKPOINT} is byte-stable
 * across calls and tagged `cache_control: ephemeral`; everything after it is the
 * per-call tail and left uncached. When no sentinel is present the whole prompt
 * is treated as static and cached. Other providers cache stable prefixes on
 * their own, so we return no options for them.
 *
 * The using class must implement {@see HasProviderOptions}
 * and expose `instructions()`.
 */
trait CachesSystemPrompt
{
    public const CACHE_BREAKPOINT = '<!-- cache:static -->';

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $lab = $provider instanceof Lab ? $provider : Lab::tryFrom($provider);

        if ($lab !== Lab::Anthropic) {
            return [];
        }

        return $this->anthropicCachedSystem((string) $this->instructions());
    }

    /**
     * @return array<string, mixed>
     */
    private function anthropicCachedSystem(string $instructions): array
    {
        if (trim($instructions) === '') {
            return [];
        }

        $position = strpos($instructions, self::CACHE_BREAKPOINT);

        if ($position === false) {
            return ['system' => [[
                'type' => 'text',
                'text' => trim($instructions),
                'cache_control' => ['type' => 'ephemeral'],
            ]]];
        }

        $static = rtrim(substr($instructions, 0, $position));
        $dynamic = ltrim(substr($instructions, $position + strlen(self::CACHE_BREAKPOINT)));

        $blocks = [];

        if ($static !== '') {
            $blocks[] = [
                'type' => 'text',
                'text' => $static,
                'cache_control' => ['type' => 'ephemeral'],
            ];
        }

        if ($dynamic !== '') {
            $blocks[] = [
                'type' => 'text',
                'text' => $dynamic,
            ];
        }

        return $blocks === [] ? [] : ['system' => $blocks];
    }
}
