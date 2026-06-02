<?php

use App\Providers\AppServiceProvider;
use Tests\TestCase;

uses(TestCase::class);

function callHeal(): void
{
    $provider = new AppServiceProvider(app());
    $reflection = new ReflectionMethod($provider, 'healStaleNativePhpSecret');
    $reflection->invoke($provider);
}

beforeEach(function () {
    $this->originalSecret = getenv('NATIVEPHP_SECRET');
    putenv('NATIVEPHP_SECRET');
    unset($_ENV['NATIVEPHP_SECRET'], $_SERVER['NATIVEPHP_SECRET']);
});

afterEach(function () {
    if ($this->originalSecret === false) {
        putenv('NATIVEPHP_SECRET');
        unset($_ENV['NATIVEPHP_SECRET'], $_SERVER['NATIVEPHP_SECRET']);
    } else {
        putenv('NATIVEPHP_SECRET='.$this->originalSecret);
        $_ENV['NATIVEPHP_SECRET'] = $this->originalSecret;
        $_SERVER['NATIVEPHP_SECRET'] = $this->originalSecret;
    }
});

it('overrides the cached secret when it differs from the runtime env', function () {
    config()->set('nativephp-internal.running', true);
    config()->set('nativephp-internal.secret', 'stale-secret-from-previous-launch');
    putenv('NATIVEPHP_SECRET=fresh-secret-this-launch');
    $_ENV['NATIVEPHP_SECRET'] = 'fresh-secret-this-launch';
    $_SERVER['NATIVEPHP_SECRET'] = 'fresh-secret-this-launch';

    callHeal();

    expect(config('nativephp-internal.secret'))->toBe('fresh-secret-this-launch');
});

it('leaves the cached secret alone when it already matches the runtime env', function () {
    config()->set('nativephp-internal.running', true);
    config()->set('nativephp-internal.secret', 'matching-secret');
    putenv('NATIVEPHP_SECRET=matching-secret');
    $_ENV['NATIVEPHP_SECRET'] = 'matching-secret';
    $_SERVER['NATIVEPHP_SECRET'] = 'matching-secret';

    callHeal();

    expect(config('nativephp-internal.secret'))->toBe('matching-secret');
});

it('is a no-op outside the NativePHP runtime', function () {
    config()->set('nativephp-internal.running', false);
    config()->set('nativephp-internal.secret', 'cached-secret');
    putenv('NATIVEPHP_SECRET=different-env-secret');
    $_ENV['NATIVEPHP_SECRET'] = 'different-env-secret';
    $_SERVER['NATIVEPHP_SECRET'] = 'different-env-secret';

    callHeal();

    expect(config('nativephp-internal.secret'))->toBe('cached-secret');
});

it('is a no-op when the env var is missing', function () {
    config()->set('nativephp-internal.running', true);
    config()->set('nativephp-internal.secret', 'cached-secret');

    callHeal();

    expect(config('nativephp-internal.secret'))->toBe('cached-secret');
});

it('is a no-op when the env var is empty', function () {
    config()->set('nativephp-internal.running', true);
    config()->set('nativephp-internal.secret', 'cached-secret');
    putenv('NATIVEPHP_SECRET=');
    $_ENV['NATIVEPHP_SECRET'] = '';
    $_SERVER['NATIVEPHP_SECRET'] = '';

    callHeal();

    expect(config('nativephp-internal.secret'))->toBe('cached-secret');
});
