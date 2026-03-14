<?php

use App\Models\AppSetting;

test('crash_report_prompted can be set via settings endpoint', function () {
    $this->putJson(route('settings.update'), [
        'key' => 'crash_report_prompted',
        'value' => true,
    ])->assertOk()
        ->assertJsonPath('message', 'Setting updated.');

    AppSetting::clearCache();
    expect(AppSetting::get('crash_report_prompted'))->toBeTrue();
});

test('crash_report_prompted is shared via Inertia props', function () {
    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('app_settings.crash_report_prompted')
        );
});

test('crash_report_prompted defaults to false', function () {
    AppSetting::clearCache();

    $this->get(route('books.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('app_settings.crash_report_prompted', false)
        );
});
