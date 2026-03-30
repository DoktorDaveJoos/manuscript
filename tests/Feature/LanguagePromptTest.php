<?php

use App\Models\AppSetting;

test('language_prompted can be set via settings endpoint', function () {
    $this->putJson(route('settings.update'), [
        'key' => 'language_prompted',
        'value' => true,
    ])->assertOk()
        ->assertJsonPath('message', 'Setting updated.');

    AppSetting::clearCache();
    expect(AppSetting::get('language_prompted'))->toBeTrue();
});

test('locale can be set via settings endpoint', function () {
    $this->putJson(route('settings.update'), [
        'key' => 'locale',
        'value' => 'de',
    ])->assertOk()
        ->assertJsonPath('message', 'Setting updated.');

    AppSetting::clearCache();
    expect(AppSetting::get('locale'))->toBe('de');
});

test('language_prompted is shared via Inertia props', function () {
    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('app_settings.language_prompted')
        );
});

test('language_prompted defaults to false', function () {
    AppSetting::clearCache();

    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('app_settings.language_prompted', false)
        );
});

test('saved locale is shared via app_settings', function () {
    AppSetting::set('locale', 'es');
    AppSetting::clearCache();

    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('app_settings.locale', 'es')
        );
});
