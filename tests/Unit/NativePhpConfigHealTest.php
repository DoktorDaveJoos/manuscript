<?php

use App\Providers\AppServiceProvider;
use Tests\TestCase;

uses(TestCase::class);

const HEALED_ENV_VARS = ['NATIVEPHP_SECRET', 'NATIVEPHP_API_URL'];

function callHeal(): void
{
    $provider = new AppServiceProvider(app());
    $reflection = new ReflectionMethod($provider, 'healStaleNativePhpConfig');
    $reflection->invoke($provider);
}

function setHealEnv(string $name, string $value): void
{
    putenv("{$name}={$value}");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

beforeEach(function () {
    $this->originalEnv = [];

    foreach (HEALED_ENV_VARS as $name) {
        $this->originalEnv[$name] = getenv($name);
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }
});

afterEach(function () {
    foreach (HEALED_ENV_VARS as $name) {
        if ($this->originalEnv[$name] === false) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        } else {
            setHealEnv($name, $this->originalEnv[$name]);
        }
    }
});

it('overrides the cached secret when it differs from the runtime env', function () {
    config()->set('nativephp-internal.running', true);
    config()->set('nativephp-internal.secret', 'stale-secret-from-previous-launch');
    setHealEnv('NATIVEPHP_SECRET', 'fresh-secret-this-launch');

    callHeal();

    expect(config('nativephp-internal.secret'))->toBe('fresh-secret-this-launch');
});

it('leaves the cached secret alone when it already matches the runtime env', function () {
    config()->set('nativephp-internal.running', true);
    config()->set('nativephp-internal.secret', 'matching-secret');
    setHealEnv('NATIVEPHP_SECRET', 'matching-secret');

    callHeal();

    expect(config('nativephp-internal.secret'))->toBe('matching-secret');
});

it('overrides the cached api_url when it differs from the runtime env', function () {
    config()->set('nativephp-internal.running', true);
    config()->set('nativephp-internal.api_url', 'http://localhost:4000/api/');
    setHealEnv('NATIVEPHP_API_URL', 'http://localhost:4017/api/');

    callHeal();

    expect(config('nativephp-internal.api_url'))->toBe('http://localhost:4017/api/');
});

it('leaves the cached api_url alone when it already matches the runtime env', function () {
    config()->set('nativephp-internal.running', true);
    config()->set('nativephp-internal.api_url', 'http://localhost:4001/api/');
    setHealEnv('NATIVEPHP_API_URL', 'http://localhost:4001/api/');

    callHeal();

    expect(config('nativephp-internal.api_url'))->toBe('http://localhost:4001/api/');
});

it('is a no-op outside the NativePHP runtime', function () {
    config()->set('nativephp-internal.running', false);
    config()->set('nativephp-internal.secret', 'cached-secret');
    config()->set('nativephp-internal.api_url', 'http://localhost:4001/api/');
    setHealEnv('NATIVEPHP_SECRET', 'different-env-secret');
    setHealEnv('NATIVEPHP_API_URL', 'http://localhost:4099/api/');

    callHeal();

    expect(config('nativephp-internal.secret'))->toBe('cached-secret')
        ->and(config('nativephp-internal.api_url'))->toBe('http://localhost:4001/api/');
});

it('is a no-op when the env vars are missing', function () {
    config()->set('nativephp-internal.running', true);
    config()->set('nativephp-internal.secret', 'cached-secret');
    config()->set('nativephp-internal.api_url', 'http://localhost:4001/api/');

    callHeal();

    expect(config('nativephp-internal.secret'))->toBe('cached-secret')
        ->and(config('nativephp-internal.api_url'))->toBe('http://localhost:4001/api/');
});

it('is a no-op when the env vars are empty', function () {
    config()->set('nativephp-internal.running', true);
    config()->set('nativephp-internal.secret', 'cached-secret');
    config()->set('nativephp-internal.api_url', 'http://localhost:4001/api/');
    setHealEnv('NATIVEPHP_SECRET', '');
    setHealEnv('NATIVEPHP_API_URL', '');

    callHeal();

    expect(config('nativephp-internal.secret'))->toBe('cached-secret')
        ->and(config('nativephp-internal.api_url'))->toBe('http://localhost:4001/api/');
});

it('heals each key independently when only one env var is present', function () {
    config()->set('nativephp-internal.running', true);
    config()->set('nativephp-internal.secret', 'cached-secret');
    config()->set('nativephp-internal.api_url', 'http://localhost:4001/api/');
    setHealEnv('NATIVEPHP_API_URL', 'http://localhost:4017/api/');

    callHeal();

    expect(config('nativephp-internal.secret'))->toBe('cached-secret')
        ->and(config('nativephp-internal.api_url'))->toBe('http://localhost:4017/api/');
});
