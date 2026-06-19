<?php

use App\Models\AppSetting;

test('unified settings page loads', function () {
    $this->get(route('settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/index')
            ->has('settings')
            ->where('settings.send_analytics', true)
            ->has('ai_providers')
            ->has('version')
        );
});

test('update setting saves value', function () {
    $this->putJson(route('settings.update'), [
        'key' => 'show_ai_features',
        'value' => false,
    ])->assertOk()
        ->assertJsonPath('message', 'Setting updated.');

    expect(AppSetting::get('show_ai_features'))->toBeFalse();
});

test('send_analytics setting is accepted and persisted', function () {
    $this->putJson(route('settings.update'), [
        'key' => 'send_analytics',
        'value' => false,
    ])->assertOk()
        ->assertJsonPath('message', 'Setting updated.');

    AppSetting::clearCache();
    expect(AppSetting::get('send_analytics'))->toBeFalse();
});

test('send_analytics defaults to true in shared inertia props', function () {
    AppSetting::clearCache();

    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('app_settings.send_analytics', true)
        );
});

test('show_ai_features toggle persists', function () {
    AppSetting::set('show_ai_features', true);
    expect(AppSetting::showAiFeatures())->toBeTrue();

    $this->putJson(route('settings.update'), [
        'key' => 'show_ai_features',
        'value' => false,
    ])->assertOk();

    AppSetting::clearCache();
    expect(AppSetting::showAiFeatures())->toBeFalse();
});

test('invalid keys are rejected', function () {
    $this->putJson(route('settings.update'), [
        'key' => 'invalid_key',
        'value' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('key');
});

test('typewriter_mode setting saves correctly', function () {
    $this->putJson(route('settings.update'), [
        'key' => 'typewriter_mode',
        'value' => true,
    ])->assertOk();

    AppSetting::clearCache();
    expect(AppSetting::get('typewriter_mode'))->toBeTrue();
});

test('settings/appearance redirects to unified settings', function () {
    $this->get('/settings/appearance')
        ->assertRedirect('/settings');
});

test('settings/license redirects to unified settings', function () {
    $this->get('/settings/license')
        ->assertRedirect('/settings');
});

test('settings/ai redirects to unified settings', function () {
    $this->get('/settings/ai')
        ->assertRedirect('/settings');
});

// Writing style and prose pass rules are book-level settings now — their
// endpoints and merge behavior are covered in BookProseSettingsTest.
