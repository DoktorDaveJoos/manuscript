<?php

use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\License;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpResponse;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Exceptions\RateLimitedException;

/**
 * Build a fake Illuminate RequestException with a chosen status + JSON body,
 * mirroring what a provider returns through the SDK's HTTP client.
 */
function fakeProviderHttpError(int $status, array $body): RequestException
{
    return new RequestException(new HttpResponse(new Psr7Response($status, [], json_encode($body))));
}

test('unified settings returns user-facing ai providers', function () {
    $this->get(route('settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/index')
            ->has('ai_providers', count(AiProvider::userFacing()))
        );
});

test('settings exposes local providers and hides azure', function () {
    $response = $this->get(route('settings.index'));
    $providers = collect($response->original->getData()['page']['props']['ai_providers']);

    expect($providers->pluck('provider'))
        ->not->toContain('azure')
        ->toContain('ollama')
        ->toContain('openai-compatible');
});

test('ai settings index redirects to unified settings', function () {
    $this->get(route('ai-settings.index'))
        ->assertRedirect('/settings');
});

test('update ai setting saves api key and options', function () {
    License::factory()->create();
    $setting = AiSetting::factory()->create(['provider' => AiProvider::Anthropic]);

    $this->putJson(route('ai-settings.update', 'anthropic'), [
        'api_key' => 'sk-new-key-123',
        'enabled' => true,
    ])->assertOk()
        ->assertJsonPath('setting.has_api_key', true);

    $setting->refresh();
    expect($setting->api_key)->toBe('sk-new-key-123')
        ->and($setting->enabled)->toBeTrue();
});

test('update ai setting does not clear api key when not provided', function () {
    License::factory()->create();
    $setting = AiSetting::factory()->create([
        'provider' => AiProvider::Openai,
        'api_key' => 'sk-existing',
    ]);

    $this->putJson(route('ai-settings.update', 'openai'), [
        'enabled' => true,
    ])->assertOk();

    $setting->refresh();
    expect($setting->api_key)->toBe('sk-existing');
});

test('update ai setting validates enabled is required', function () {
    License::factory()->create();
    AiSetting::factory()->create(['provider' => AiProvider::Anthropic]);

    $this->putJson(route('ai-settings.update', 'anthropic'), [
        'api_key' => 'sk-key',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('enabled');
});

test('update ai setting ignores legacy fields', function () {
    License::factory()->create();
    AiSetting::factory()->create(['provider' => AiProvider::Anthropic]);

    $this->putJson(route('ai-settings.update', 'anthropic'), [
        'api_key' => 'sk-new-key',
        'base_url' => 'https://example.test',
        'api_version' => '2024-10-21',
        'azure_deployment' => 'gpt-4o',
        'embedding_model' => 'foo',
        'enabled' => true,
    ])->assertOk();

    $setting = AiSetting::query()->where('provider', 'anthropic')->first();
    expect($setting->base_url)->toBeNull()
        ->and($setting->api_version)->toBeNull()
        ->and($setting->azure_deployment)->toBeNull()
        ->and($setting->embedding_model)->toBeNull();
});

test('update ollama setting saves its endpoint and model without an api key', function () {
    AiSetting::factory()->ollama()->disabled()->create([
        'base_url' => null,
        'text_model' => null,
    ]);

    $this->putJson(route('ai-settings.update', 'ollama'), [
        'base_url' => 'http://localhost:11434',
        'text_model' => 'qwen3:8b',
        'enabled' => true,
    ])->assertOk()
        ->assertJsonPath('setting.is_configured', true)
        ->assertJsonPath('setting.has_api_key', false);

    $setting = AiSetting::query()->where('provider', 'ollama')->first();
    expect($setting->base_url)->toBe('http://localhost:11434')
        ->and($setting->text_model)->toBe('qwen3:8b')
        ->and($setting->enabled)->toBeTrue();
});

test('update openai compatible setting saves an LM Studio endpoint and model', function () {
    AiSetting::factory()->openAiCompatible()->disabled()->create();

    $this->putJson(route('ai-settings.update', 'openai-compatible'), [
        'base_url' => 'http://localhost:1234/v1',
        'text_model' => 'qwen/qwen3-8b',
        'enabled' => true,
    ])->assertOk()
        ->assertJsonPath('setting.provider', 'openai-compatible')
        ->assertJsonPath('setting.is_configured', true);

    $setting = AiSetting::query()->where('provider', 'openai-compatible')->first();
    $setting->injectConfig();

    expect(config('ai.default'))->toBe('openai-compatible')
        ->and(config('ai.providers.openai-compatible.url'))->toBe('http://localhost:1234/v1')
        ->and(config('ai.providers.openai-compatible.models.text.default'))->toBe('qwen/qwen3-8b')
        ->and(config('ai.providers.openai-compatible.models.text.cheapest'))->toBe('qwen/qwen3-8b')
        ->and(config('ai.providers.openai-compatible.models.text.smartest'))->toBe('qwen/qwen3-8b');
});

test('injecting updated local settings refreshes the resolved sdk provider', function () {
    $setting = AiSetting::factory()->openAiCompatible()->create([
        'text_model' => 'first-model',
    ]);
    $setting->injectConfig();

    expect(Ai::textProvider('openai-compatible')->defaultTextModel())->toBe('first-model');

    $setting->update(['text_model' => 'second-model']);
    $setting->injectConfig();

    expect(Ai::textProvider('openai-compatible')->defaultTextModel())->toBe('second-model');
});

test('local provider endpoint must be an http or https url', function () {
    $this->putJson(route('ai-settings.update', 'ollama'), [
        'base_url' => 'not-a-url',
        'text_model' => 'qwen3:8b',
        'enabled' => false,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('base_url');
});

test('unified settings never expose api key to frontend', function () {
    AiSetting::factory()->create([
        'provider' => AiProvider::Anthropic,
        'api_key' => 'sk-secret-key',
    ]);

    $response = $this->get(route('settings.index'));

    $response->assertOk();
    $page = $response->original->getData()['page'];
    $providers = $page['props']['ai_providers'];

    $anthropicSetting = collect($providers)->firstWhere('provider', 'anthropic');
    expect($anthropicSetting)->toHaveKey('has_api_key', true)
        ->not->toHaveKey('api_key');
});

test('test connection fails without api key', function () {
    License::factory()->create();
    AiSetting::factory()->withoutKey()->create(['provider' => AiProvider::Anthropic]);

    $this->postJson(route('ai-settings.test', 'anthropic'))
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});

test('test connection succeeds with a working key', function () {
    License::factory()->create();
    AiSetting::factory()->create(['provider' => AiProvider::Anthropic]);
    AnonymousAgent::fake(['ok']);

    $this->postJson(route('ai-settings.test', 'anthropic'))
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('test connection classifies an out-of-credits account as insufficient_credits', function () {
    License::factory()->create();
    AiSetting::factory()->create(['provider' => AiProvider::Openai]);

    $http = fakeProviderHttpError(429, [
        'error' => [
            'type' => 'insufficient_quota',
            'message' => 'You exceeded your current quota, please check your plan and billing details.',
        ],
    ]);
    AnonymousAgent::fake(fn () => throw RateLimitedException::forProvider('openai', previous: $http));

    $this->postJson(route('ai-settings.test', 'openai'))
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('kind', 'insufficient_credits');
});

test('test connection classifies a rejected key as invalid_key', function () {
    License::factory()->create();
    AiSetting::factory()->create(['provider' => AiProvider::Anthropic]);

    AnonymousAgent::fake(fn () => throw fakeProviderHttpError(401, [
        'error' => ['message' => 'invalid x-api-key'],
    ]));

    $this->postJson(route('ai-settings.test', 'anthropic'))
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('kind', 'invalid_key');
});

test('update openrouter setting saves successfully', function () {
    License::factory()->create();
    AiSetting::factory()->create(['provider' => AiProvider::OpenRouter]);

    $this->putJson(route('ai-settings.update', 'openrouter'), [
        'api_key' => 'sk-or-key-123',
        'enabled' => true,
    ])->assertOk()
        ->assertJsonPath('setting.has_api_key', true);
});

test('unified settings exposes only api key fields per provider', function () {
    $response = $this->get(route('settings.index'));

    $response->assertOk();
    $providers = collect($response->original->getData()['page']['props']['ai_providers']);

    $anthropic = $providers->firstWhere('provider', 'anthropic');
    expect($anthropic)
        ->toHaveKey('has_api_key')
        ->toHaveKey('masked_api_key')
        ->toHaveKey('label')
        ->not->toHaveKey('base_url')
        ->not->toHaveKey('api_version')
        ->not->toHaveKey('azure_deployment')
        ->not->toHaveKey('embedding_model')
        ->not->toHaveKey('supports_embeddings');
});

test('unified settings exposes connection fields only for local providers', function () {
    AiSetting::factory()->openAiCompatible()->create();

    $response = $this->get(route('settings.index'));
    $providers = collect($response->original->getData()['page']['props']['ai_providers']);
    $provider = $providers->firstWhere('provider', 'openai-compatible');

    expect($provider)
        ->toHaveKey('base_url', 'http://localhost:1234/v1')
        ->toHaveKey('text_model', 'local-model')
        ->toHaveKey('requires_api_key', false)
        ->toHaveKey('requires_base_url', true)
        ->toHaveKey('requires_text_model', true)
        ->toHaveKey('is_configured', true)
        ->not->toHaveKey('api_key');
});

test('delete key removes api key from provider', function () {
    License::factory()->create();
    $setting = AiSetting::factory()->create([
        'provider' => AiProvider::Anthropic,
        'api_key' => 'sk-secret-key',
    ]);

    $this->deleteJson(route('ai-settings.delete-key', 'anthropic'))
        ->assertOk()
        ->assertJsonPath('setting.has_api_key', false)
        ->assertJsonPath('setting.masked_api_key', null);

    $setting->refresh();
    expect($setting->api_key)->toBeNull();
});

test('selecting a provider disables all others', function () {
    License::factory()->create();

    AiSetting::factory()->create(['provider' => AiProvider::Anthropic, 'enabled' => true]);
    AiSetting::factory()->create(['provider' => AiProvider::Openai, 'enabled' => false]);

    $this->putJson(route('ai-settings.update', 'openai'), [
        'enabled' => true,
    ])->assertOk();

    expect(AiSetting::query()->where('provider', 'openai')->first()->enabled)->toBeTrue();
    expect(AiSetting::query()->where('provider', 'anthropic')->first()->enabled)->toBeFalse();
});

test('saving credentials for an inactive provider preserves the active provider', function () {
    AiSetting::factory()->create([
        'provider' => AiProvider::Anthropic,
        'enabled' => true,
    ]);
    AiSetting::factory()->withoutKey()->create([
        'provider' => AiProvider::Openai,
        'enabled' => false,
    ]);

    $this->putJson(route('ai-settings.update', 'openai'), [
        'api_key' => 'sk-openai-key',
        'enabled' => false,
    ])->assertOk();

    expect(AiSetting::query()->where('provider', 'anthropic')->first()->enabled)->toBeTrue()
        ->and(AiSetting::query()->where('provider', 'openai')->first()->enabled)->toBeFalse()
        ->and(AiSetting::query()->where('provider', 'openai')->first()->api_key)->toBe('sk-openai-key');
});

test('an unconfigured provider cannot replace the working provider', function () {
    AiSetting::factory()->create([
        'provider' => AiProvider::Anthropic,
        'enabled' => true,
    ]);
    AiSetting::factory()->withoutKey()->create([
        'provider' => AiProvider::Openai,
        'enabled' => false,
    ]);

    $this->putJson(route('ai-settings.update', 'openai'), [
        'enabled' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('enabled');

    expect(AiSetting::query()->where('provider', 'anthropic')->first()->enabled)->toBeTrue()
        ->and(AiSetting::query()->where('provider', 'openai')->first()->enabled)->toBeFalse();
});
