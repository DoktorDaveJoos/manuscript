<?php

use App\Models\AppSetting;

test('appearance page loads with default settings', function () {
    $this->get(route('settings.appearance'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/appearance')
            ->has('settings')
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

test('settings redirect to appearance', function () {
    $this->get('/settings')
        ->assertRedirect('/settings/appearance');
});

test('license page loads', function () {
    $this->get(route('settings.license'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/license')
        );
});
