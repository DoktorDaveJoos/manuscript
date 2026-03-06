<?php

use App\Models\License;

test('activate with valid key stores license', function () {
    $license = License::factory()->make();

    $this->postJson(route('license.activate'), ['license_key' => $license->key])
        ->assertOk()
        ->assertJsonPath('message', 'License activated successfully.');

    expect(License::query()->count())->toBe(1);
    expect(License::active())->not->toBeNull();
    expect(License::active()->key)->toBe($license->key);
});

test('activate with invalid key returns 422', function () {
    $license = License::factory()->make();
    $parts = explode('.', $license->key, 3);
    $tampered = $parts[0].'.'.$parts[1].'.X'.substr($parts[2], 1);

    $this->postJson(route('license.activate'), ['license_key' => $tampered])
        ->assertStatus(422);

    expect(License::query()->count())->toBe(0);
});

test('activate with malformed key returns validation error', function () {
    $this->postJson(route('license.activate'), ['license_key' => 'not-a-key'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('license_key');
});

test('deactivate removes license', function () {
    License::factory()->create();

    expect(License::isActive())->toBeTrue();

    $this->postJson(route('license.deactivate'))
        ->assertOk()
        ->assertJsonPath('message', 'License deactivated.');

    expect(License::isActive())->toBeFalse();
    expect(License::query()->count())->toBe(0);
});

test('license status is shared in inertia props', function () {
    $response = $this->get(route('ai-settings.index'));

    $page = $response->original->getData()['page'];
    expect($page['props']['license']['active'])->toBeFalse();

    License::factory()->create();

    $response = $this->get(route('ai-settings.index'));
    $page = $response->original->getData()['page'];
    expect($page['props']['license']['active'])->toBeTrue();
    expect($page['props']['license']['masked_key'])->toStartWith('MANU.');
});

test('isActive returns correct state', function () {
    expect(License::isActive())->toBeFalse();

    License::factory()->create();
    expect(License::isActive())->toBeTrue();
});
