<?php

use Native\Desktop\Facades\AutoUpdater;

test('check for updates triggers auto updater', function () {
    AutoUpdater::shouldReceive('checkForUpdates')->once();

    $this->postJson(route('update.check'))
        ->assertOk()
        ->assertJsonPath('status', 'checking');
});

test('install update triggers quit and install', function () {
    AutoUpdater::shouldReceive('quitAndInstall')->once();

    $this->postJson(route('update.install'))
        ->assertOk()
        ->assertJsonPath('status', 'installing');
});
