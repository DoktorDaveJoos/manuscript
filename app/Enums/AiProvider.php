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
            self::Azure => Lab::Azure,
            self::OpenRouter => Lab::OpenRouter,
        };
    }

    public function supportsEmbeddings(): bool
    {
        return match ($this) {
            self::Openai, self::Gemini, self::Mistral, self::Azure => true,
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
            self::Azure => 'Azure OpenAI',
            self::OpenRouter => 'OpenRouter',
        };
    }

    public function requiresApiKey(): bool
    {
        return $this !== self::Ollama;
    }

    public function requiresBaseUrl(): bool
    {
        return match ($this) {
            self::Azure, self::Ollama => true,
            default => false,
        };
    }
}
