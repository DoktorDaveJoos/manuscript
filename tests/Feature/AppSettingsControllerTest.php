<?php

use App\Models\AppSetting;

test('unified settings page loads', function () {
    $this->get(route('settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/index')
            ->has('settings')
            ->has('ai_providers')
            ->has('writing_style_text')
            ->has('acknowledgment_text')
            ->has('about_author_text')
            ->has('prose_pass_rules')
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

test('global writing style saves', function () {
    $this->putJson(route('settings.writing-style.update'), [
        'writing_style_text' => 'Dark, moody prose with short sentences.',
    ])->assertOk();

    AppSetting::clearCache();
    expect(AppSetting::get('writing_style_text'))->toBe('Dark, moody prose with short sentences.');
});

test('acknowledgment text saves', function () {
    $this->putJson(route('settings.acknowledgment.update'), [
        'acknowledgment_text' => 'I would like to thank my family and friends.',
    ])->assertOk();

    AppSetting::clearCache();
    expect(AppSetting::get('acknowledgment_text'))->toBe('I would like to thank my family and friends.');
});

test('about author text saves', function () {
    $this->putJson(route('settings.about-author.update'), [
        'about_author_text' => 'Jane Doe is an author based in Portland.',
    ])->assertOk();

    AppSetting::clearCache();
    expect(AppSetting::get('about_author_text'))->toBe('Jane Doe is an author based in Portland.');
});

test('global prose pass rules save', function () {
    $rules = \App\Models\Book::defaultProsePassRules();
    $rules[0]['enabled'] = false;

    $this->putJson(route('settings.prose-pass-rules.update'), [
        'rules' => $rules,
    ])->assertOk();

    AppSetting::clearCache();
    $saved = json_decode(AppSetting::get('prose_pass_rules'), true);
    expect($saved[0]['enabled'])->toBeFalse();
});
