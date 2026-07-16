<?php

declare(strict_types=1);

use App\Services\DatabaseStartupService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Sleep;
use Native\Desktop\ChildProcess;
use Native\Desktop\Client\Client;
use Native\Desktop\Contracts\QueueWorker as QueueWorkerContract;
use Native\Desktop\NativeServiceProvider;
use Tests\TestCase;

uses(TestCase::class);

const NATIVE_SERVICE_PROVIDER_RUNTIME_PATCH = 'scripts/nativephp-patches/files/src/NativeServiceProvider.php';

const NATIVE_SERVICE_PROVIDER_VENDOR_FILE = 'vendor/nativephp/desktop/src/NativeServiceProvider.php';

const CHILD_PROCESS_RUNTIME_PATCH = 'scripts/nativephp-patches/files/src/ChildProcess.php';

it('atomically replaces all cached NativePHP runtime values with this launch values', function (): void {
    $runtimeValues = [
        'NATIVEPHP_RUNNING' => 'true',
        'NATIVEPHP_STORAGE_PATH' => '/tmp/manuscript-runtime/storage',
        'NATIVEPHP_DATABASE_PATH' => '/tmp/manuscript-runtime/database.sqlite',
        'NATIVEPHP_SECRET' => 'fresh-runtime-secret',
        'NATIVEPHP_API_URL' => 'http://127.0.0.1:4317/api/',
    ];
    $originalValues = [];

    try {
        foreach ($runtimeValues as $name => $value) {
            $originalValues[$name] = getenv($name);
            putenv("{$name}={$value}");
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        config()->set([
            'nativephp-internal.running' => false,
            'nativephp-internal.storage_path' => '/stale/storage',
            'nativephp-internal.database_path' => '/stale/database.sqlite',
            'nativephp-internal.secret' => 'cached-secret',
            'nativephp-internal.api_url' => 'http://127.0.0.1:4000/api/',
        ]);

        $provider = new NativeServiceProvider(app());
        $reconcileRuntimeConfig = new ReflectionMethod($provider, 'reconcileRuntimeConfig');
        $reconcileRuntimeConfig->invoke($provider);

        expect(config('nativephp-internal.running'))->toBeTrue()
            ->and(config('nativephp-internal.storage_path'))->toBe('/tmp/manuscript-runtime/storage')
            ->and(config('nativephp-internal.database_path'))->toBe('/tmp/manuscript-runtime/database.sqlite')
            ->and(config('nativephp-internal.secret'))->toBe('fresh-runtime-secret')
            ->and(config('nativephp-internal.api_url'))->toBe('http://127.0.0.1:4317/api/');
    } finally {
        foreach ($originalValues as $name => $value) {
            if ($value === false) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);

                continue;
            }

            putenv("{$name}={$value}");
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
    }
});

it('rejects an incomplete NativePHP runtime without partially applying its values', function (): void {
    $runtimeValues = [
        'NATIVEPHP_RUNNING' => 'true',
        'NATIVEPHP_STORAGE_PATH' => '/tmp/manuscript-runtime/storage',
        'NATIVEPHP_DATABASE_PATH' => '/tmp/manuscript-runtime/database.sqlite',
        'NATIVEPHP_SECRET' => '',
        'NATIVEPHP_API_URL' => 'http://127.0.0.1:4317/api/',
    ];
    $originalValues = [];
    $cachedValues = [
        'nativephp-internal.running' => true,
        'nativephp-internal.storage_path' => '/stale/storage',
        'nativephp-internal.database_path' => '/stale/database.sqlite',
        'nativephp-internal.secret' => 'cached-secret',
        'nativephp-internal.api_url' => 'http://127.0.0.1:4000/api/',
    ];

    try {
        foreach ($runtimeValues as $name => $value) {
            $originalValues[$name] = getenv($name);
            putenv("{$name}={$value}");
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        config()->set($cachedValues);

        $provider = new NativeServiceProvider(app());
        $reconcileRuntimeConfig = new ReflectionMethod($provider, 'reconcileRuntimeConfig');

        expect(fn () => $reconcileRuntimeConfig->invoke($provider))
            ->toThrow(RuntimeException::class, 'NATIVEPHP_SECRET');

        foreach ($cachedValues as $configKey => $cachedValue) {
            expect(config($configKey))->toBe($cachedValue);
        }
    } finally {
        foreach ($originalValues as $name => $value) {
            if ($value === false) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);

                continue;
            }

            putenv("{$name}={$value}");
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
    }
});

it('disables cached NativePHP running state outside a live desktop launch', function (): void {
    $runtimeVariables = [
        'NATIVEPHP_RUNNING',
        'NATIVEPHP_STORAGE_PATH',
        'NATIVEPHP_DATABASE_PATH',
        'NATIVEPHP_SECRET',
        'NATIVEPHP_API_URL',
    ];
    $originalValues = [];

    try {
        foreach ($runtimeVariables as $name) {
            $originalValues[$name] = getenv($name);
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }

        config()->set([
            'nativephp-internal.running' => true,
            'nativephp-internal.storage_path' => '/stale/storage',
            'nativephp-internal.database_path' => '/stale/database.sqlite',
            'nativephp-internal.secret' => 'cached-secret',
            'nativephp-internal.api_url' => 'http://127.0.0.1:4000/api/',
        ]);

        $provider = new NativeServiceProvider(app());
        $reconcileRuntimeConfig = new ReflectionMethod($provider, 'reconcileRuntimeConfig');
        $reconcileRuntimeConfig->invoke($provider);

        expect(config('nativephp-internal.running'))->toBeFalse()
            ->and(config('nativephp-internal.storage_path'))->toBe('/stale/storage')
            ->and(config('nativephp-internal.database_path'))->toBe('/stale/database.sqlite')
            ->and(config('nativephp-internal.secret'))->toBe('cached-secret')
            ->and(config('nativephp-internal.api_url'))->toBe('http://127.0.0.1:4000/api/');
    } finally {
        foreach ($originalValues as $name => $value) {
            if ($value === false) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);

                continue;
            }

            putenv("{$name}={$value}");
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
    }
});

it('reconciles the complete live NativePHP runtime before configuring the app', function (): void {
    $patch = (string) file_get_contents(base_path(NATIVE_SERVICE_PROVIDER_RUNTIME_PATCH));

    $mergePosition = strpos($patch, '$this->mergeConfigFrom(');
    $reconcilePosition = strpos($patch, '$this->reconcileRuntimeConfig();');
    $configurePosition = strpos($patch, '$this->configureApp();');

    expect($patch)
        ->toContain("'nativephp-internal.running' => true")
        ->toContain("'nativephp-internal.storage_path' => \$runtimeValues['NATIVEPHP_STORAGE_PATH']")
        ->toContain("'nativephp-internal.database_path' => \$runtimeValues['NATIVEPHP_DATABASE_PATH']")
        ->toContain("'nativephp-internal.secret' => 'NATIVEPHP_SECRET'")
        ->toContain("'nativephp-internal.api_url' => 'NATIVEPHP_API_URL'")
        ->toContain('throw new \RuntimeException(')
        ->toContain('Missing required NativePHP runtime values:')
        ->toContain("config(['nativephp-internal.running' => false]);")
        ->and($mergePosition)->toBeInt()
        ->and($reconcilePosition)->toBeInt()
        ->and($configurePosition)->toBeInt()
        ->and($mergePosition)->toBeLessThan($reconcilePosition)
        ->and($reconcilePosition)->toBeLessThan($configurePosition);
});

it('runs the database readiness barrier before starting queue workers', function (): void {
    $patch = (string) file_get_contents(base_path(NATIVE_SERVICE_PROVIDER_RUNTIME_PATCH));
    $orchestratorPosition = strpos($patch, 'protected function startBackgroundServices(): void');
    $orchestrator = substr($patch, $orchestratorPosition);
    $databasePosition = strpos($orchestrator, 'DatabaseStartupService::class');
    $queuePosition = strpos($orchestrator, '$this->fireUpQueueWorkers();');

    expect($orchestratorPosition)->toBeInt()
        ->and($patch)->toContain('$this->app->booted(fn () => $this->startBackgroundServices());')
        ->and($databasePosition)->toBeInt()
        ->and($queuePosition)->toBeInt()
        ->and($databasePosition)->toBeLessThan($queuePosition);

    expect($patch)
        ->not->toContain("if (! \$this->app->runningInConsole()) {\n            \$this->fireUpQueueWorkers();");
});

it('keeps the browser fallback schema guard separate from the native background orchestrator', function (): void {
    $provider = (string) file_get_contents(base_path('app/Providers/AppServiceProvider.php'));

    expect($provider)
        ->toContain("if (! config('nativephp-internal.running')) {")
        ->toContain('$this->app->booted(function () {')
        ->toContain('$this->app->make(DatabaseStartupService::class)->ensureSchema();');
});

it('skips queue workers when database readiness fails without aborting boot', function (): void {
    $originalDatabaseStartup = app(DatabaseStartupService::class);
    $originalExceptionHandler = app(ExceptionHandler::class);

    $databaseStartup = Mockery::mock(DatabaseStartupService::class);
    $databaseStartup->shouldReceive('ensureSchema')
        ->once()
        ->andReturnFalse();

    $queueWorker = Mockery::mock(QueueWorkerContract::class);
    $queueWorker->shouldNotReceive('up');

    $exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $exceptionHandler->shouldReceive('report')
        ->once()
        ->andThrow(new RuntimeException('exception reporter unavailable'));

    app()->instance(DatabaseStartupService::class, $databaseStartup);
    app()->instance(QueueWorkerContract::class, $queueWorker);
    app()->instance(ExceptionHandler::class, $exceptionHandler);

    try {
        $provider = new NativeServiceProvider(app());
        $startBackgroundServices = new ReflectionMethod($provider, 'startBackgroundServices');
        $startBackgroundServices->invoke($provider);

        expect(true)->toBeTrue();
    } finally {
        app()->instance(DatabaseStartupService::class, $originalDatabaseStartup);
        app()->forgetInstance(QueueWorkerContract::class);
        app()->instance(ExceptionHandler::class, $originalExceptionHandler);
    }
});

it('retries transient queue worker startup failures until the worker is acknowledged', function (): void {
    $attempts = 0;
    $queueWorker = Mockery::mock(QueueWorkerContract::class);
    $queueWorker->shouldReceive('up')
        ->times(3)
        ->andReturnUsing(function () use (&$attempts): void {
            $attempts++;

            if ($attempts < 3) {
                throw new RuntimeException('queue worker not ready');
            }
        });

    Sleep::fake();
    config()->set('nativephp.queue_workers', ['default' => []]);
    app()->instance(QueueWorkerContract::class, $queueWorker);

    try {
        $provider = new NativeServiceProvider(app());
        $fireUpQueueWorkers = new ReflectionMethod($provider, 'fireUpQueueWorkers');
        $fireUpQueueWorkers->invoke($provider);

        expect($attempts)->toBe(3);
        Sleep::assertSequence([
            Sleep::for(100)->milliseconds(),
            Sleep::for(300)->milliseconds(),
        ]);
    } finally {
        Sleep::fake(false);
        app()->forgetInstance(QueueWorkerContract::class);
    }
});

it('reports exhausted queue worker startup failures without aborting provider registration', function (): void {
    $patch = (string) file_get_contents(base_path(NATIVE_SERVICE_PROVIDER_RUNTIME_PATCH));
    $methodPosition = strpos($patch, 'protected function fireUpQueueWorkers(): void');
    $method = substr($patch, $methodPosition);

    $configPosition = strpos($method, "\$queueConfigs = QueueConfig::fromConfigArray(config('nativephp.queue_workers'));");
    $foreachPosition = strpos($method, 'foreach ($queueConfigs as $queueConfig)');
    $workerTryPosition = strpos($method, 'try {', $foreachPosition);
    $retryPosition = strpos($method, 'retry(', $workerTryPosition);
    $backoffPosition = strpos($method, '[100, 300]', $retryPosition);
    $startPosition = strpos($method, '->up($queueConfig)');
    $workerCatchPosition = strpos($method, 'catch (\\Throwable $exception)', $workerTryPosition);
    $workerReportPosition = strpos($method, 'rescue(fn () => report($exception), report: false);', $workerCatchPosition);

    expect($methodPosition)->toBeInt()
        ->and($method)->toContain('rescue(fn () => report($exception), report: false);')
        ->and($configPosition)->toBeInt()
        ->and($foreachPosition)->toBeInt()
        ->and($workerTryPosition)->toBeInt()
        ->and($retryPosition)->toBeInt()
        ->and($backoffPosition)->toBeInt()
        ->and($startPosition)->toBeInt()
        ->and($workerCatchPosition)->toBeInt()
        ->and($workerReportPosition)->toBeInt()
        ->and($configPosition)->toBeLessThan($foreachPosition)
        ->and($foreachPosition)->toBeLessThan($workerTryPosition)
        ->and($workerTryPosition)->toBeLessThan($retryPosition)
        ->and($retryPosition)->toBeLessThan($backoffPosition)
        ->and($backoffPosition)->toBeLessThan($startPosition)
        ->and($startPosition)->toBeLessThan($workerCatchPosition)
        ->and($workerCatchPosition)->toBeLessThan($workerReportPosition);
});

it('does not abort boot when queue startup and exception reporting both fail', function (): void {
    $originalExceptionHandler = app(ExceptionHandler::class);

    $queueWorker = Mockery::mock(QueueWorkerContract::class);
    $queueWorker->shouldReceive('up')
        ->times(3)
        ->andThrow(new RuntimeException('queue worker could not start'));

    $exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $exceptionHandler->shouldReceive('report')
        ->once()
        ->andThrow(new RuntimeException('exception reporter unavailable'));

    config()->set('nativephp.queue_workers', ['default' => []]);
    app()->instance(QueueWorkerContract::class, $queueWorker);
    app()->instance(ExceptionHandler::class, $exceptionHandler);
    Sleep::fake();

    try {
        $provider = new NativeServiceProvider(app());
        $fireUpQueueWorkers = new ReflectionMethod($provider, 'fireUpQueueWorkers');
        $fireUpQueueWorkers->invoke($provider);

        expect(true)->toBeTrue();
        Sleep::assertSequence([
            Sleep::for(100)->milliseconds(),
            Sleep::for(300)->milliseconds(),
        ]);
    } finally {
        Sleep::fake(false);
        app()->forgetInstance(QueueWorkerContract::class);
        app()->instance(ExceptionHandler::class, $originalExceptionHandler);
    }
});

it('does not abort boot when queue configuration parsing and reporting both fail', function (): void {
    $originalExceptionHandler = app(ExceptionHandler::class);

    $exceptionHandler = Mockery::mock(ExceptionHandler::class);
    $exceptionHandler->shouldReceive('report')
        ->once()
        ->andThrow(new RuntimeException('exception reporter unavailable'));

    config()->set('nativephp.queue_workers');
    app()->instance(ExceptionHandler::class, $exceptionHandler);

    try {
        $provider = new NativeServiceProvider(app());
        $fireUpQueueWorkers = new ReflectionMethod($provider, 'fireUpQueueWorkers');
        $fireUpQueueWorkers->invoke($provider);

        expect(true)->toBeTrue();
    } finally {
        app()->instance(ExceptionHandler::class, $originalExceptionHandler);
    }
});

it('rejects an Electron child-process startup error so queue retries can observe it', function (): void {
    $childProcess = new ChildProcess(Mockery::mock(Client::class));
    $fromRuntimeProcess = new ReflectionMethod($childProcess, 'fromRuntimeProcess');

    expect(fn () => $fromRuntimeProcess->invoke($childProcess, [
        'error' => 'queue process failed to spawn',
        'settings' => [],
    ]))->toThrow(RuntimeException::class, 'queue process failed to spawn');

    expect((string) file_get_contents(base_path(CHILD_PROCESS_RUNTIME_PATCH)))
        ->toContain("if (isset(\$process['error']))")
        ->toContain('NativePHP child process failed to start:');
});

it('ships the runtime hardening through the vendor patch pipeline', function (): void {
    $apply = (string) file_get_contents(base_path('scripts/nativephp-patches/apply.sh'));
    $testWorkflow = (string) file_get_contents(base_path('.github/workflows/tests.yml'));

    expect($apply)->toContain('apply "src/NativeServiceProvider.php"')
        ->toContain('apply "src/ChildProcess.php"')
        ->and(base_path(NATIVE_SERVICE_PROVIDER_VENDOR_FILE))->toBeReadableFile()
        ->and($testWorkflow)->toContain('- name: Apply NativePHP vendor patches')
        ->toContain('run: bash scripts/nativephp-patches/apply.sh');
});
