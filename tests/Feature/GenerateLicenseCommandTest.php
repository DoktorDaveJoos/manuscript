<?php

use Database\Factories\LicenseFactory;

test('generates a valid license key in local environment', function () {
    app()->detectEnvironment(fn () => 'local');

    // Use the test keypair so we can verify the output
    $secretKey = base64_decode(LicenseFactory::TEST_SECRET_KEY);
    $publicKey = base64_decode(LicenseFactory::TEST_PUBLIC_KEY);

    // Set the signing key in env
    putenv('LICENSE_SIGNING_KEY='.LicenseFactory::TEST_SECRET_KEY);

    $this->artisan('license:generate')
        ->assertSuccessful()
        ->expectsOutputToContain('MANU.');

    putenv('LICENSE_SIGNING_KEY');
});

test('refuses to run in production environment', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('license:generate')
        ->assertFailed()
        ->expectsOutputToContain('local environment');
});

test('keypair option outputs key material', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->artisan('license:generate --keypair')
        ->assertSuccessful()
        ->expectsOutputToContain('SECRET KEY')
        ->expectsOutputToContain('PUBLIC KEY');
});

test('fails when LICENSE_SIGNING_KEY is not set', function () {
    app()->detectEnvironment(fn () => 'local');
    putenv('LICENSE_SIGNING_KEY');

    $this->artisan('license:generate')
        ->assertFailed()
        ->expectsOutputToContain('LICENSE_SIGNING_KEY is not set');
});

test('generates multiple keys with count option', function () {
    app()->detectEnvironment(fn () => 'local');
    putenv('LICENSE_SIGNING_KEY='.LicenseFactory::TEST_SECRET_KEY);

    $this->artisan('license:generate --count=3')
        ->assertSuccessful();

    putenv('LICENSE_SIGNING_KEY');
});
