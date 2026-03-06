<?php

use App\Models\License;

uses(Tests\TestCase::class);

test('validate accepts a factory-generated license key', function () {
    $license = License::factory()->make();

    expect(License::validate($license->key))->toBeTrue();
});

test('validate rejects a tampered signature', function () {
    $license = License::factory()->make();
    $parts = explode('.', $license->key, 3);

    // Flip the first character of the signature
    $tampered = $parts[0].'.'.$parts[1].'.X'.substr($parts[2], 1);

    expect(License::validate($tampered))->toBeFalse();
});

test('validate rejects old HMAC format', function () {
    expect(License::validate('MANU-AB12-XXXX-YYYY'))->toBeFalse();
});

test('validate rejects key signed with wrong keypair', function () {
    $wrongKeypair = sodium_crypto_sign_keypair();
    $wrongSecret = sodium_crypto_sign_secretkey($wrongKeypair);

    $id = strtoupper(bin2hex(random_bytes(4)));
    $signature = sodium_crypto_sign_detached($id, $wrongSecret);
    $signatureB64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    $key = 'MANU.'.$id.'.'.$signatureB64;

    expect(License::validate($key))->toBeFalse();
});

test('validate rejects malformed formats', function () {
    expect(License::validate(''))->toBeFalse();
    expect(License::validate('INVALID-KEY'))->toBeFalse();
    expect(License::validate('MANU.AB12.short'))->toBeFalse();
    expect(License::validate('NOPE.A3F29B01.aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaA'))->toBeFalse();
    expect(License::validate('MANU.lowercase.aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaA'))->toBeFalse();
});
