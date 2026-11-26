<?php

namespace App\Enums;

use Laravel\Ai\Enums\Lab;

enum AiProvider: string
{
    case Anthropic = 'anthropic';
    case Openai = 'openai';
    case Gemini = 'gemini';
    case Groq = 'groq';
    case Xai = 'xai';
    case DeepSeek = 'deepseek';
    case Mistral = 'mistral';
    case Ollama = 'ollama';
    case OpenAiCompatible = 'openai-compatible';
    case Azure = 'azure';
    case OpenRouter = 'openrouter';

    public function toLab(): Lab
    {
        return match ($this) {
            self::Anthropic => Lab::Anthropic,
            self::Openai => Lab::OpenAI,
            self::Gemini => Lab::Gemini,
            self::Groq => Lab::Groq,
            self::Xai => Lab::xAI,
            self::DeepSeek => Lab::DeepSeek,
            self::Mistral => Lab::Mistral,
            self::Ollama => Lab::Ollama,
            self::OpenAiCompatible => Lab::OpenAICompatible,
            self::Azure => Lab::Azure,
            self::OpenRouter => Lab::OpenRouter,
        };
    }

    public function supportsEmbeddings(): bool
    {
        return match ($this) {
            // Mistral's mistral-embed emits fixed 1024-dim vectors, incompatible
            // with our float[1536] chunk_embeddings sqlite-vec schema.
            self::Openai, self::Gemini, self::Azure => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Anthropic => 'Anthropic',
            self::Openai => 'OpenAI',
            self::Gemini => 'Google Gemini',
            self::Groq => 'Groq',
            self::Xai => 'xAI',
            self::DeepSeek => 'DeepSeek',
            self::Mistral => 'Mistral',
            self::Ollama => 'Ollama',
            self::OpenAiCompatible => 'OpenAI Compatible',
            self::Azure => 'Azure OpenAI',
            self::OpenRouter => 'OpenRouter',
        };
    }

    public function requiresApiKey(): bool
    {
        return ! in_array($this, [self::Ollama, self::OpenAiCompatible], true);
    }

    public function requiresBaseUrl(): bool
    {
        return match ($this) {
            self::Azure, self::Ollama, self::OpenAiCompatible => true,
            default => false,
        };
    }

    public function requiresTextModel(): bool
    {
        return in_array($this, [self::Ollama, self::OpenAiCompatible], true);
    }

    /**
     * Providers configurable with just an API key.
     * Azure is excluded because it needs deployment-specific settings.
     *
     * @return list<self>
     */
    public static function userFacing(): array
    {
        return [
            self::Anthropic,
            self::Openai,
            self::Gemini,
            self::Groq,
            self::Xai,
            self::DeepSeek,
            self::Mistral,
            self::Ollama,
            self::OpenAiCompatible,
            self::OpenRouter,
        ];
    }
}
