<?php

use App\Models\License;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

test('active returns the activated license', function () {
    expect(License::active())->toBeNull();

    $license = License::factory()->create();

    expect(License::active())->not->toBeNull();
    expect(License::active()->id)->toBe($license->id);
});

test('active returns null when license is deactivated', function () {
    License::factory()->deactivated()->create();

    expect(License::active())->toBeNull();
});

test('isActive returns correct state', function () {
    expect(License::isActive())->toBeFalse();

    License::factory()->create();
    expect(License::isActive())->toBeTrue();
});

test('verifyMeta accepts matching store and product ids', function () {
    config([
        'app.lemonsqueezy.store_id' => 12345,
        'app.lemonsqueezy.product_id' => 67890,
    ]);

    expect(License::verifyMeta([
        'store_id' => 12345,
        'product_id' => 67890,
    ]))->toBeTrue();
});

test('verifyMeta rejects wrong store id', function () {
    config([
        'app.lemonsqueezy.store_id' => 12345,
        'app.lemonsqueezy.product_id' => 67890,
    ]);

    expect(License::verifyMeta([
        'store_id' => 99999,
        'product_id' => 67890,
    ]))->toBeFalse();
});

test('verifyMeta rejects wrong product id', function () {
    config([
        'app.lemonsqueezy.store_id' => 12345,
        'app.lemonsqueezy.product_id' => 67890,
    ]);

    expect(License::verifyMeta([
        'store_id' => 12345,
        'product_id' => 11111,
    ]))->toBeFalse();
});

test('verifyMeta rejects missing keys', function () {
    config([
        'app.lemonsqueezy.store_id' => 12345,
        'app.lemonsqueezy.product_id' => 67890,
    ]);

    expect(License::verifyMeta([]))->toBeFalse();
});

test('needsRevalidation returns true when never validated', function () {
    $license = License::factory()->make(['last_validated_at' => null]);

    expect($license->needsRevalidation())->toBeTrue();
});

test('needsRevalidation returns true when older than 7 days', function () {
    $license = License::factory()->stale()->make();

    expect($license->needsRevalidation())->toBeTrue();
});

test('needsRevalidation returns false when recently validated', function () {
    $license = License::factory()->make(['last_validated_at' => now()]);

    expect($license->needsRevalidation())->toBeFalse();
});
