<?php

/**
 * Per-model pricing in microdollars per 1M tokens.
 * 1 USD = 1,000,000 microdollars.
 */
return [

    'default' => [
        'input' => 3_000_000,
        'output' => 15_000_000,
    ],

    'models' => [
        // Anthropic
        'claude-sonnet-4-20250514' => ['input' => 3_000_000, 'output' => 15_000_000],
        'claude-3-5-sonnet-20241022' => ['input' => 3_000_000, 'output' => 15_000_000],
        'claude-3-5-haiku-20241022' => ['input' => 800_000, 'output' => 4_000_000],
        'claude-3-haiku-20240307' => ['input' => 250_000, 'output' => 1_250_000],
        'claude-opus-4-20250514' => ['input' => 15_000_000, 'output' => 75_000_000],
        'claude-3-opus-20240229' => ['input' => 15_000_000, 'output' => 75_000_000],

        // OpenAI
        'gpt-4o' => ['input' => 2_500_000, 'output' => 10_000_000],
        'gpt-4o-mini' => ['input' => 150_000, 'output' => 600_000],
        'gpt-4-turbo' => ['input' => 10_000_000, 'output' => 30_000_000],
        'gpt-4.1' => ['input' => 2_000_000, 'output' => 8_000_000],
        'gpt-4.1-mini' => ['input' => 400_000, 'output' => 1_600_000],
        'gpt-4.1-nano' => ['input' => 100_000, 'output' => 400_000],
        'o3-mini' => ['input' => 1_100_000, 'output' => 4_400_000],

        // Google Gemini
        'gemini-2.0-flash' => ['input' => 100_000, 'output' => 400_000],
        'gemini-2.5-flash-preview-04-17' => ['input' => 150_000, 'output' => 600_000],
        'gemini-2.5-pro-preview-05-06' => ['input' => 1_250_000, 'output' => 10_000_000],
        'gemini-1.5-pro' => ['input' => 1_250_000, 'output' => 5_000_000],
        'gemini-1.5-flash' => ['input' => 75_000, 'output' => 300_000],

        // Groq
        'llama-3.3-70b-versatile' => ['input' => 590_000, 'output' => 790_000],
        'llama-3.1-8b-instant' => ['input' => 50_000, 'output' => 80_000],
        'mixtral-8x7b-32768' => ['input' => 240_000, 'output' => 240_000],

        // xAI
        'grok-3' => ['input' => 3_000_000, 'output' => 15_000_000],
        'grok-3-mini' => ['input' => 300_000, 'output' => 500_000],

        // DeepSeek
        'deepseek-chat' => ['input' => 270_000, 'output' => 1_100_000],
        'deepseek-reasoner' => ['input' => 550_000, 'output' => 2_190_000],

        // Mistral
        'mistral-large-latest' => ['input' => 2_000_000, 'output' => 6_000_000],
        'mistral-small-latest' => ['input' => 200_000, 'output' => 600_000],

        // Ollama (local, zero cost)
        'llama3.2' => ['input' => 0, 'output' => 0],
        'llama3.1' => ['input' => 0, 'output' => 0],
        'mistral' => ['input' => 0, 'output' => 0],
        'gemma2' => ['input' => 0, 'output' => 0],
    ],

    'embedding_models' => [
        'text-embedding-3-small' => 20_000,
        'text-embedding-3-large' => 130_000,
        'text-embedding-ada-002' => 100_000,
        'voyage-3' => 60_000,
        'voyage-3-lite' => 20_000,
    ],

    'embedding_default' => 20_000,

];
