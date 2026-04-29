<?php

use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\License;

test('unified settings returns all ai providers', function () {
    $this->get(route('settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/index')
            ->has('ai_providers', count(AiProvider::cases()))
        );
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

test('update ai setting validates base_url must be valid url', function () {
    License::factory()->create();
    AiSetting::factory()->create(['provider' => AiProvider::Ollama]);

    $this->putJson(route('ai-settings.update', 'ollama'), [
        'base_url' => 'not-a-url',
        'enabled' => true,
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

test('update azure setting saves api_version', function () {
    License::factory()->create();
    AiSetting::factory()->create(['provider' => AiProvider::Azure]);

    $this->putJson(route('ai-settings.update', 'azure'), [
        'api_key' => 'sk-azure-key',
        'base_url' => 'https://my-resource.openai.azure.com',
        'api_version' => '2024-10-21',
        'azure_deployment' => 'gpt-4o',
        'enabled' => true,
    ])->assertOk()
        ->assertJsonPath('setting.api_version', '2024-10-21')
        ->assertJsonPath('setting.azure_deployment', 'gpt-4o');

    $setting = AiSetting::query()->where('provider', 'azure')->first();
    expect($setting->api_version)->toBe('2024-10-21')
        ->and($setting->base_url)->toBe('https://my-resource.openai.azure.com');
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

test('unified settings includes requires_api_key and requires_base_url', function () {
    $response = $this->get(route('settings.index'));

    $response->assertOk();
    $page = $response->original->getData()['page'];
    $providers = collect($page['props']['ai_providers']);

    $ollama = $providers->firstWhere('provider', 'ollama');
    expect($ollama)
        ->toHaveKey('requires_api_key', false)
        ->toHaveKey('requires_base_url', true);

    $anthropic = $providers->firstWhere('provider', 'anthropic');
    expect($anthropic)
        ->toHaveKey('requires_api_key', true)
        ->toHaveKey('requires_base_url', false);

    $azure = $providers->firstWhere('provider', 'azure');
    expect($azure)
        ->toHaveKey('requires_api_key', true)
        ->toHaveKey('requires_base_url', true)
        ->toHaveKey('api_version');
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
