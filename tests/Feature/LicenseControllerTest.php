<?php

use App\Models\License;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'app.lemonsqueezy.store_id' => 12345,
        'app.lemonsqueezy.product_id' => 67890,
    ]);
});

function fakeLsActivation(string $licenseKey, array $overrides = []): array
{
    return array_merge([
        'activated' => true,
        'instance' => [
            'id' => (string) Str::uuid(),
            'name' => gethostname(),
        ],
        'license_key' => [
            'id' => 111222,
            'status' => 'active',
            'key' => $licenseKey,
            'activation_limit' => 5,
            'activation_usage' => 1,
            'expires_at' => null,
        ],
        'meta' => [
            'store_id' => 12345,
            'product_id' => 67890,
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'product_name' => 'Manuscript Pro',
        ],
    ], $overrides);
}

test('activate with valid key stores license', function () {
    $key = (string) Str::uuid();

    Http::fake([
        'api.lemonsqueezy.com/v1/licenses/activate' => Http::response(fakeLsActivation($key)),
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
    $key = (string) Str::uuid();

    Http::fake([
        'api.lemonsqueezy.com/v1/licenses/activate' => Http::response([
            'activated' => false,
            'error' => 'The license key was not found.',
        ], 404),
    ]);

    $this->postJson(route('license.activate'), ['license_key' => $key])
        ->assertStatus(422);

    expect(License::query()->count())->toBe(0);
});

test('activate with wrong product returns 422', function () {
    $key = (string) Str::uuid();

    Http::fake([
        'api.lemonsqueezy.com/v1/licenses/activate' => Http::response(
            fakeLsActivation($key, [
                'meta' => [
                    'store_id' => 99999,
                    'product_id' => 99999,
                    'customer_name' => 'Jane Doe',
                    'customer_email' => 'jane@example.com',
                    'product_name' => 'Other Product',
                ],
            ])
        ),
    ]);

    $this->postJson(route('license.activate'), ['license_key' => $key])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This license key is not valid for Manuscript.');

    expect(License::query()->count())->toBe(0);
});

test('activate with malformed key returns validation error', function () {
    $this->postJson(route('license.activate'), ['license_key' => 'not-a-uuid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('license_key');
});

test('activate returns 503 when offline', function () {
    Http::fake(fn (Request $request) => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'));

    $key = (string) Str::uuid();

    $this->postJson(route('license.activate'), ['license_key' => $key])
        ->assertStatus(503);
});

test('deactivate calls LS API and removes license', function () {
    $license = License::factory()->create();

    Http::fake([
        'api.lemonsqueezy.com/v1/licenses/deactivate' => Http::response([
            'deactivated' => true,
        ]),
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
        'api.lemonsqueezy.com/v1/licenses/validate' => Http::response([
            'valid' => true,
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

test('isActive returns correct state', function () {
    expect(License::isActive())->toBeFalse();

    License::factory()->create();
    expect(License::isActive())->toBeTrue();
});
