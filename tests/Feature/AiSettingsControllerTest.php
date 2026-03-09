<?php

use App\Enums\AiProvider;
use App\Models\AiSetting;
use App\Models\License;

test('ai settings index returns all providers', function () {
    $this->get(route('ai-settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/ai-providers')
            ->has('settings', count(AiProvider::cases()))
        );
});

test('ai settings index creates default settings for unconfigured providers', function () {
    expect(AiSetting::query()->count())->toBe(0);

    $this->get(route('ai-settings.index'))->assertOk();

    expect(AiSetting::query()->count())->toBe(count(AiProvider::cases()));
});

test('update ai setting saves api key and options', function () {
    License::factory()->create();
    $setting = AiSetting::factory()->create(['provider' => AiProvider::Anthropic]);

    $this->putJson(route('ai-settings.update', 'anthropic'), [
        'api_key' => 'sk-new-key-123',
        'text_model' => 'claude-sonnet-4-20250514',
        'enabled' => true,
    ])->assertOk()
        ->assertJsonPath('setting.has_api_key', true)
        ->assertJsonPath('setting.text_model', 'claude-sonnet-4-20250514');

    $setting->refresh();
    expect($setting->api_key)->toBe('sk-new-key-123')
        ->and($setting->text_model)->toBe('claude-sonnet-4-20250514')
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

test('ai settings never expose api key to frontend', function () {
    AiSetting::factory()->create([
        'provider' => AiProvider::Anthropic,
        'api_key' => 'sk-secret-key',
    ]);

    $response = $this->get(route('ai-settings.index'));

    $response->assertOk();
    $page = $response->original->getData()['page'];
    $settings = $page['props']['settings'];

    $anthropicSetting = collect($settings)->firstWhere('provider', 'anthropic');
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
        'text_model' => 'gpt-4o',
        'enabled' => true,
    ])->assertOk()
        ->assertJsonPath('setting.api_version', '2024-10-21');

    $setting = AiSetting::query()->where('provider', 'azure')->first();
    expect($setting->api_version)->toBe('2024-10-21')
        ->and($setting->base_url)->toBe('https://my-resource.openai.azure.com');
});

test('update openrouter setting saves successfully', function () {
    License::factory()->create();
    AiSetting::factory()->create(['provider' => AiProvider::OpenRouter]);

    $this->putJson(route('ai-settings.update', 'openrouter'), [
        'api_key' => 'sk-or-key-123',
        'text_model' => 'anthropic/claude-sonnet-4-20250514',
        'enabled' => true,
    ])->assertOk()
        ->assertJsonPath('setting.has_api_key', true)
        ->assertJsonPath('setting.text_model', 'anthropic/claude-sonnet-4-20250514');
});

test('ai settings index includes requires_api_key and requires_base_url', function () {
    $response = $this->get(route('ai-settings.index'));

    $response->assertOk();
    $page = $response->original->getData()['page'];
    $settings = collect($page['props']['settings']);

    $ollama = $settings->firstWhere('provider', 'ollama');
    expect($ollama)
        ->toHaveKey('requires_api_key', false)
        ->toHaveKey('requires_base_url', true);

    $anthropic = $settings->firstWhere('provider', 'anthropic');
    expect($anthropic)
        ->toHaveKey('requires_api_key', true)
        ->toHaveKey('requires_base_url', false);

    $azure = $settings->firstWhere('provider', 'azure');
    expect($azure)
        ->toHaveKey('requires_api_key', true)
        ->toHaveKey('requires_base_url', true)
        ->toHaveKey('api_version');
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
