<?php

use App\Models\License;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function fakePolarActivation(string $key, array $overrides = []): array
{
    $licenseKeyId = (string) Str::uuid();
    $activationId = (string) Str::uuid();

    $defaults = [
        'id' => $activationId,
        'license_key_id' => $licenseKeyId,
        'label' => gethostname(),
        'meta' => [],
        'created_at' => now()->toIso8601String(),
        'modified_at' => null,
        'license_key' => [
            'id' => $licenseKeyId,
            'organization_id' => 'test-org-id',
            'customer_id' => (string) Str::uuid(),
            'customer' => [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
            ],
            'benefit_id' => (string) Str::uuid(),
            'key' => $key,
            'display_key' => substr($key, 0, 8).'****',
            'status' => 'granted',
            'limit_activations' => 5,
            'usage' => 1,
            'limit_usage' => null,
            'validations' => 0,
            'last_validated_at' => null,
            'expires_at' => null,
        ],
    ];

    return array_replace_recursive($defaults, $overrides);
}

test('activate with valid key stores license', function () {
    $key = 'MANU-AAAA-BBBB-CCCC';

    Http::fake([
        'api.polar.sh/v1/license-keys/activate' => Http::response(fakePolarActivation($key)),
    ]);

    $this->postJson(route('license.activate'), ['license_key' => $key])
        ->assertOk()
        ->assertJsonPath('message', 'License activated successfully.');

    expect(License::query()->count())->toBe(1);
    $license = License::active();
    expect($license)->not->toBeNull();
    expect($license->license_key)->toBe($key);
    expect($license->instance_id)->not->toBeNull();
    expect($license->customer_name)->toBe('Jane Doe');
    expect($license->last_validated_at)->not->toBeNull();
});

test('activate with invalid key returns 422', function () {
    $key = 'INVALID-KEY';

    Http::fake([
        'api.polar.sh/v1/license-keys/activate' => Http::response([
            'detail' => 'License key not found.',
        ], 404),
    ]);

    $this->postJson(route('license.activate'), ['license_key' => $key])
        ->assertStatus(422);

    expect(License::query()->count())->toBe(0);
});

test('activate with revoked key returns 422', function () {
    $key = 'MANU-AAAA-BBBB-CCCC';

    Http::fake([
        'api.polar.sh/v1/license-keys/activate' => Http::response(
            fakePolarActivation($key, [
                'license_key' => [
                    'status' => 'revoked',
                ],
            ])
        ),
    ]);

    $this->postJson(route('license.activate'), ['license_key' => $key])
        ->assertStatus(422);

    expect(License::query()->count())->toBe(0);
});

test('activate with missing key returns validation error', function () {
    $this->postJson(route('license.activate'), ['license_key' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('license_key');
});

test('activate returns 503 when offline', function () {
    Http::fake(fn (Request $request) => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'));

    $this->postJson(route('license.activate'), ['license_key' => 'MANU-AAAA-BBBB-CCCC'])
        ->assertStatus(503);
});

test('deactivate calls Polar API and removes license', function () {
    $license = License::factory()->create();

    Http::fake([
        'api.polar.sh/v1/license-keys/deactivate' => Http::response(null, 204),
    ]);

    expect(License::isActive())->toBeTrue();

    $this->postJson(route('license.deactivate'))
        ->assertOk()
        ->assertJsonPath('message', 'License deactivated.');

    expect(License::isActive())->toBeFalse();
    expect(License::query()->count())->toBe(0);
});

test('deactivate returns 503 when offline and keeps license', function () {
    License::factory()->create();

    Http::fake(fn (Request $request) => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'));

    $this->postJson(route('license.deactivate'))
        ->assertStatus(503);

    expect(License::isActive())->toBeTrue();
});

test('revalidate updates last_validated_at on success', function () {
    $license = License::factory()->stale()->create();

    Http::fake([
        'api.polar.sh/v1/license-keys/validate' => Http::response([
            'id' => $license->license_key_id,
            'status' => 'granted',
            'key' => $license->license_key,
        ]),
    ]);

    $this->postJson(route('license.revalidate'))
        ->assertOk()
        ->assertJsonPath('revalidated', true);

    $license->refresh();
    expect($license->last_validated_at->isToday())->toBeTrue();
});

test('revalidate silently skips when offline', function () {
    $license = License::factory()->stale()->create();

    Http::fake(fn (Request $request) => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'));

    $this->postJson(route('license.revalidate'))
        ->assertOk()
        ->assertJsonPath('revalidated', false);

    expect(License::isActive())->toBeTrue();
});

test('revalidate skips when recently validated', function () {
    License::factory()->create(['last_validated_at' => now()]);

    Http::fake();

    $this->postJson(route('license.revalidate'))
        ->assertOk()
        ->assertJsonPath('revalidated', false);

    Http::assertNothingSent();
});

test('license status is shared in inertia props', function () {
    $response = $this->get(route('settings.index'));

    $page = $response->original->getData()['page'];
    expect($page['props']['license']['active'])->toBeFalse();

    License::factory()->create();

    $response = $this->get(route('settings.index'));
    $page = $response->original->getData()['page'];
    expect($page['props']['license']['active'])->toBeTrue();
    expect($page['props']['license']['masked_key'])->not->toBeNull();
});
