<?php

use App\Models\AppSetting;
use App\Providers\NativeAppServiceProvider;
use App\Services\BackupService;
use App\Services\StaleUpdateGuard;
use Native\Desktop\Facades\AutoUpdater;
use Native\Desktop\Facades\Window;

beforeEach(function () {
    config(['nativephp.updater.enabled' => true]);

    $this->mock(BackupService::class)
        ->shouldReceive('applyPending')
        ->once();
    $this->mock(StaleUpdateGuard::class)
        ->shouldReceive('reconcile')
        ->once();

    $pendingWindow = Mockery::mock();
    foreach (['title', 'backgroundColor', 'width', 'height', 'minWidth', 'minHeight'] as $method) {
        $pendingWindow->shouldReceive($method)->once()->andReturnSelf();
    }

    Window::shouldReceive('open')->once()->andReturn($pendingWindow);
});

test('native startup checks for updates by default on a fresh install', function () {
    AppSetting::query()->where('key', 'auto_update')->delete();
    AppSetting::clearCache();

    AutoUpdater::shouldReceive('checkForUpdates')->once();

    (new NativeAppServiceProvider)->boot();
});

test('native startup does not check for updates when automatic updates are disabled', function () {
    AppSetting::set('auto_update', false);

    AutoUpdater::shouldReceive('checkForUpdates')->never();

    (new NativeAppServiceProvider)->boot();
});
