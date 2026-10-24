<?php

use App\Services\PolarService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('uses production API URL when APP_ENV is not local', function () {
    Http::fake(['*' => Http::response(['license_key' => ['status' => 'granted']], 200)]);

    app()->detectEnvironment(fn () => 'production');

    $service = new PolarService;
    $service->activate('test-key', 'test-label');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.polar.sh/v1/customer-portal/license-keys/activate';
    });
});

test('uses sandbox API URL when APP_ENV is local', function () {
    Http::fake(['*' => Http::response(['license_key' => ['status' => 'granted']], 200)]);

    app()->detectEnvironment(fn () => 'local');

    $service = new PolarService;
    $service->activate('test-key', 'test-label');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://sandbox-api.polar.sh/v1/customer-portal/license-keys/activate';
    });
});

test('calls the public customer-portal endpoints without an access token', function () {
    Http::fake([
        '*/activate' => Http::response(['id' => 'activation-id', 'license_key' => ['status' => 'granted']]),
        '*/validate' => Http::response(['status' => 'granted']),
        '*/deactivate' => Http::response(null, 204),
    ]);

    $service = new PolarService;

    expect($service->activate('test-key', 'test-label')['success'])->toBeTrue();
    expect($service->validate('test-key', 'activation-id')['success'])->toBeTrue();
    expect($service->deactivate('test-key', 'activation-id')['success'])->toBeTrue();

    Http::assertSentCount(3);
    Http::assertNotSent(fn (Request $request) => $request->hasHeader('Authorization'));
});

test('sends the organization id and key with every request', function () {
    Http::fake([
        '*/activate' => Http::response(['id' => 'activation-id', 'license_key' => ['status' => 'granted']]),
        '*/validate' => Http::response(['status' => 'granted']),
        '*/deactivate' => Http::response(null, 204),
    ]);

    $service = new PolarService;
    $service->activate('test-key', 'test-label');
    $service->validate('test-key', 'activation-id');
    $service->deactivate('test-key', 'activation-id');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/customer-portal/license-keys/activate')
        && $request['key'] === 'test-key'
        && $request['organization_id'] === 'test-org-id'
        && $request['label'] === 'test-label');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/customer-portal/license-keys/validate')
        && $request['key'] === 'test-key'
        && $request['organization_id'] === 'test-org-id'
        && $request['activation_id'] === 'activation-id');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/customer-portal/license-keys/deactivate')
        && $request['key'] === 'test-key'
        && $request['organization_id'] === 'test-org-id'
        && $request['activation_id'] === 'activation-id');
});

test('keeps a string error detail from Polar', function () {
    Http::fake(['*' => Http::response([
        'error' => 'NotPermitted',
        'detail' => 'License key activation limit reached.',
    ], 403)]);

    $result = (new PolarService)->activate('test-key', 'test-label');

    expect($result['success'])->toBeFalse();
    expect($result['data']['detail'])->toBe('License key activation limit reached.');
});

test('drops a structured validation detail so callers never show an array', function () {
    Http::fake(['*' => Http::response([
        'error' => 'RequestValidationError',
        'detail' => [['type' => 'uuid_parsing', 'loc' => ['body', 'organization_id'], 'msg' => 'Input should be a valid UUID']],
    ], 422)]);

    $service = new PolarService;

    $activation = $service->activate('test-key', 'test-label');
    expect($activation['success'])->toBeFalse();
    expect($activation['data'])->not->toHaveKey('detail');

    $validation = $service->validate('test-key', 'activation-id');
    expect($validation['success'])->toBeFalse();
    expect($validation['data'])->not->toHaveKey('detail');
});

test('the Polar organization access token is never bundled into the app', function () {
    expect(config('nativephp.cleanup_env_keys'))->toContain('POLAR_ACCESS_TOKEN');
    expect(config('app.polar'))->not->toHaveKey('access_token');

    expect((string) file_get_contents(base_path('.github/workflows/publish.yml')))->not->toContain('POLAR_ACCESS_TOKEN');
    expect((string) file_get_contents(base_path('.env.example')))->not->toContain('POLAR_ACCESS_TOKEN');
});
