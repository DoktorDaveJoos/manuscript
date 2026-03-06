<?php

use App\Enums\AiProvider;

test('all providers have a valid Lab mapping', function () {
    foreach (AiProvider::cases() as $provider) {
        expect($provider->toLab())->toBeInstanceOf(\Laravel\Ai\Enums\Lab::class);
    }
});

test('all providers have labels', function () {
    foreach (AiProvider::cases() as $provider) {
        expect($provider->label())->toBeString()->not->toBeEmpty();
    }
});

test('there are 10 providers', function () {
    expect(AiProvider::cases())->toHaveCount(10);
});

test('azure and ollama require base url', function () {
    expect(AiProvider::Azure->requiresBaseUrl())->toBeTrue()
        ->and(AiProvider::Ollama->requiresBaseUrl())->toBeTrue();
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

test('ollama does not require api key', function () {
    expect(AiProvider::Ollama->requiresApiKey())->toBeFalse();
});

test('azure supports embeddings', function () {
    expect(AiProvider::Azure->supportsEmbeddings())->toBeTrue();
});
