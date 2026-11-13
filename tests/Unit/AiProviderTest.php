<?php

use App\Enums\AiProvider;
use Laravel\Ai\Enums\Lab;

test('all providers have a valid Lab mapping', function () {
    foreach (AiProvider::cases() as $provider) {
        expect($provider->toLab())->toBeInstanceOf(Lab::class);
    }
});

test('all providers have labels', function () {
    foreach (AiProvider::cases() as $provider) {
        expect($provider->label())->toBeString()->not->toBeEmpty();
    }
});

test('there are 11 providers', function () {
    expect(AiProvider::cases())->toHaveCount(11);
});

test('supported providers are exposed in settings', function () {
    expect(AiProvider::userFacing())->toBe([
        AiProvider::Anthropic,
        AiProvider::Openai,
        AiProvider::Gemini,
        AiProvider::Groq,
        AiProvider::Xai,
        AiProvider::DeepSeek,
        AiProvider::Mistral,
        AiProvider::Ollama,
        AiProvider::OpenAiCompatible,
        AiProvider::OpenRouter,
    ])->not->toContain(AiProvider::Azure);
});

test('azure and local providers require base url', function () {
    expect(AiProvider::Azure->requiresBaseUrl())->toBeTrue()
        ->and(AiProvider::Ollama->requiresBaseUrl())->toBeTrue()
        ->and(AiProvider::OpenAiCompatible->requiresBaseUrl())->toBeTrue();
});

test('other providers do not require base url', function () {
    $noBaseUrl = [
        AiProvider::Anthropic,
        AiProvider::Openai,
        AiProvider::Gemini,
        AiProvider::Groq,
        AiProvider::Xai,
        AiProvider::DeepSeek,
        AiProvider::Mistral,
        AiProvider::OpenRouter,
    ];

    foreach ($noBaseUrl as $provider) {
        expect($provider->requiresBaseUrl())->toBeFalse("{$provider->value} should not require base URL");
    }
});

test('local providers do not require api keys', function () {
    expect(AiProvider::Ollama->requiresApiKey())->toBeFalse()
        ->and(AiProvider::OpenAiCompatible->requiresApiKey())->toBeFalse();
});

test('local providers require an explicit text model', function () {
    expect(AiProvider::Ollama->requiresTextModel())->toBeTrue()
        ->and(AiProvider::OpenAiCompatible->requiresTextModel())->toBeTrue()
        ->and(AiProvider::Openai->requiresTextModel())->toBeFalse();
});

test('azure supports embeddings', function () {
    expect(AiProvider::Azure->supportsEmbeddings())->toBeTrue();
});
