<?php

use App\Providers\NativeAppServiceProvider;
use Tests\TestCase;

uses(TestCase::class);

it('does not cap PHP execution time so queue daemons are bounded by Laravel timeouts only', function () {
    $ini = (new NativeAppServiceProvider)->phpIni();

    expect($ini)->toHaveKey('max_execution_time', '0');
});
