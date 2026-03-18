<?php

use App\Services\PolarService;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

test('uses production API URL when APP_ENV is not local', function () {
    Http::fake(['*' => Http::response(['license_key' => ['status' => 'granted']], 200)]);

    app()->detectEnvironment(fn () => 'production');

    $service = new PolarService;
    $service->activate('test-key', 'test-label');

    Http::assertSent(function ($request) {
        return str_starts_with($request->url(), 'https://api.polar.sh/');
    });
});

test('uses sandbox API URL when APP_ENV is local', function () {
    Http::fake(['*' => Http::response(['license_key' => ['status' => 'granted']], 200)]);

    app()->detectEnvironment(fn () => 'local');

    $service = new PolarService;
    $service->activate('test-key', 'test-label');

    Http::assertSent(function ($request) {
        return str_starts_with($request->url(), 'https://sandbox-api.polar.sh/');
    });
});
