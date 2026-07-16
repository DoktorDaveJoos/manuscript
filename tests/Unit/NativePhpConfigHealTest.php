<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Tests\TestCase;

uses(TestCase::class);

const HEALED_ENV_VARS = [
    'NATIVEPHP_RUNNING',
    'NATIVEPHP_STORAGE_PATH',
    'NATIVEPHP_DATABASE_PATH',
    'NATIVEPHP_SECRET',
    'NATIVEPHP_API_URL',
];

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

function setCompleteNativePhpRuntimeEnv(): void
{
    setHealEnv('NATIVEPHP_RUNNING', 'true');
    setHealEnv('NATIVEPHP_STORAGE_PATH', '/tmp/manuscript-runtime/storage');
    setHealEnv('NATIVEPHP_DATABASE_PATH', '/tmp/manuscript-runtime/database.sqlite');
    setHealEnv('NATIVEPHP_SECRET', 'fresh-secret-this-launch');
    setHealEnv('NATIVEPHP_API_URL', 'http://localhost:4017/api/');
}

beforeEach(function (): void {
    $this->originalEnv = [];

    foreach (HEALED_ENV_VARS as $name) {
        $this->originalEnv[$name] = getenv($name);
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }
});

afterEach(function (): void {
    foreach (HEALED_ENV_VARS as $name) {
        if ($this->originalEnv[$name] === false) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        } else {
            setHealEnv($name, $this->originalEnv[$name]);
        }
    }
});

it('atomically overrides the complete cached runtime tuple', function (): void {
    config()->set([
        'nativephp-internal.running' => false,
        'nativephp-internal.storage_path' => '/stale/storage',
        'nativephp-internal.database_path' => '/stale/database.sqlite',
        'nativephp-internal.secret' => 'stale-secret',
        'nativephp-internal.api_url' => 'http://localhost:4000/api/',
    ]);
    setCompleteNativePhpRuntimeEnv();

    callHeal();

    expect(config('nativephp-internal.running'))->toBeTrue()
        ->and(config('nativephp-internal.storage_path'))->toBe('/tmp/manuscript-runtime/storage')
        ->and(config('nativephp-internal.database_path'))->toBe('/tmp/manuscript-runtime/database.sqlite')
        ->and(config('nativephp-internal.secret'))->toBe('fresh-secret-this-launch')
        ->and(config('nativephp-internal.api_url'))->toBe('http://localhost:4017/api/');
});

it('rejects an incomplete live runtime without partially changing cached config', function (): void {
    $cachedConfig = [
        'nativephp-internal.running' => true,
        'nativephp-internal.storage_path' => '/stale/storage',
        'nativephp-internal.database_path' => '/stale/database.sqlite',
        'nativephp-internal.secret' => 'stale-secret',
        'nativephp-internal.api_url' => 'http://localhost:4000/api/',
    ];
    config()->set($cachedConfig);
    setCompleteNativePhpRuntimeEnv();
    setHealEnv('NATIVEPHP_SECRET', '');

    expect(fn () => callHeal())
        ->toThrow(RuntimeException::class, 'NATIVEPHP_SECRET');

    foreach ($cachedConfig as $configKey => $cachedValue) {
        expect(config($configKey))->toBe($cachedValue);
    }
});

it('does not use cached running state outside a live NativePHP runtime', function (): void {
    config()->set([
        'nativephp-internal.running' => true,
        'nativephp-internal.storage_path' => '/cached/storage',
        'nativephp-internal.database_path' => '/cached/database.sqlite',
        'nativephp-internal.secret' => 'cached-secret',
        'nativephp-internal.api_url' => 'http://localhost:4001/api/',
    ]);

    callHeal();

    expect(config('nativephp-internal.running'))->toBeTrue()
        ->and(config('nativephp-internal.storage_path'))->toBe('/cached/storage')
        ->and(config('nativephp-internal.database_path'))->toBe('/cached/database.sqlite')
        ->and(config('nativephp-internal.secret'))->toBe('cached-secret')
        ->and(config('nativephp-internal.api_url'))->toBe('http://localhost:4001/api/');
});
