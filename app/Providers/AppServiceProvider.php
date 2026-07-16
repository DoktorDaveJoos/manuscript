<?php

namespace App\Providers;

use App\Console\Commands\OptimizeCommand;
use App\Database\ResilientMigrationRepository;
use App\Database\ResilientMigrator;
use App\Database\SqliteVecConnector;
use App\Listeners\RecordAiTokenUsage;
use App\Models\Act;
use App\Models\Beat;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Observers\BoardChangeObserver;
use App\Services\BackupEncryptionService;
use App\Services\BackupService;
use App\Services\DatabaseStartupService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Sentry\Laravel\Integration;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // SqliteVecConnector both loads the sqlite-vec extension AND applies
        // performance pragmas on every PDO connect, so it covers both the
        // default 'sqlite' connection and NativePHP's runtime 'nativephp' one.
        $this->app->bind('db.connector.sqlite', SqliteVecConnector::class);

        // Override Laravel's OptimizeCommand so `php artisan optimize` (invoked
        // on every production launch by NativePHP's Electron main process) does
        // NOT run route:cache. See App\Console\Commands\OptimizeCommand for the
        // full rationale and Sentry issue 112195649.
        $this->app->singleton('command.optimize', OptimizeCommand::class);

        // BackupService must follow whichever SQLite file the *active*
        // connection points at. NativePHP swaps `database.default` to a
        // separate `nativephp` connection at boot, while the seed `sqlite`
        // connection still points at the empty starter DB — naming the
        // sqlite connection directly would silently back up the seed.
        // Mirrors SqliteVecConnector::markerPath() for consistency.
        $this->app->singleton(BackupService::class, function ($app) {
            $default = config('database.default');
            $databasePath = config("database.connections.{$default}.database")
                ?: database_path('nativephp.sqlite');

            return new BackupService(
                $app->make(BackupEncryptionService::class),
                $databasePath,
            );
        });

        // Harden the migration repository so creating the `migrations` table
        // is idempotent. NativePHP runs `migrate --force` on every launch; on
        // Windows SQLite/WAL the table-existence probe can under-report an
        // existing table, making migrate:install throw "table already exists"
        // and abort the whole migration (Sentry 123909138). `extend` replaces
        // the binding even though MigrationServiceProvider is deferred — the
        // extender is applied after the deferred provider builds the instance.
        $this->app->extend('migration.repository', function ($repository, $app) {
            $migrations = $app['config']['database.migrations'];
            $table = is_array($migrations) ? ($migrations['table'] ?? 'migrations') : $migrations;

            return new ResilientMigrationRepository($app['db'], $table);
        });

        // Snapshot-and-restore migrator: SQLite migrations are NOT wrapped in
        // transactions, so a mid-run failure would leave partial DDL behind
        // and wedge every subsequent launch-time `migrate --force` on
        // "already exists". Extenders run before afterResolving callbacks, so
        // loadMigrationsFrom() path registrations still land on this instance.
        $this->app->extend('migrator', function ($migrator, $app) {
            return new ResilientMigrator(
                $app['migration.repository'],
                $app['db'],
                $app['files'],
                $app['events'],
            );
        });

        // The startup service needs to distinguish real CLI contexts from
        // NativePHP's cli-server requests — capture the flag at build time.
        $this->app->singleton(
            DatabaseStartupService::class,
            fn ($app) => new DatabaseStartupService($app, $app->runningInConsole()),
        );

        // In browser development there is no NativePHP background-service
        // orchestrator, so retain the ordinary booted schema guard. The
        // patched NativeServiceProvider owns this ordering in desktop mode:
        // database readiness first, then queue workers.
        if (! config('nativephp-internal.running')) {
            $this->app->booted(function () {
                $this->app->make(DatabaseStartupService::class)->ensureSchema();
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSentry();
        $this->healStaleNativePhpConfig();

        Event::listen(AgentPrompted::class, RecordAiTokenUsage::class);
        Event::listen(AgentStreamed::class, RecordAiTokenUsage::class);

        PlotPoint::observe(BoardChangeObserver::class);
        Beat::observe(BoardChangeObserver::class);
        Storyline::observe(BoardChangeObserver::class);
        Act::observe(BoardChangeObserver::class);
    }

    /**
     * Reconcile per-launch NativePHP config values from the runtime env.
     *
     * Electron generates a fresh `NATIVEPHP_SECRET` and control-API port
     * (`NATIVEPHP_API_URL`) per launch, along with the live storage/database
     * paths. The cached config holds values from whichever launch last rebuilt
     * it. The patched NativePHP provider atomically reconciles the full tuple
     * before it configures the desktop runtime; this remains a request-time
     * defense in depth. Reading env() directly sidesteps the config cache.
     */
    protected function healStaleNativePhpConfig(): void
    {
        $isRunning = filter_var(
            env('NATIVEPHP_RUNNING'),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE,
        );

        if ($isRunning !== true) {
            return;
        }

        $requiredRuntimeValues = [
            'nativephp-internal.storage_path' => 'NATIVEPHP_STORAGE_PATH',
            'nativephp-internal.database_path' => 'NATIVEPHP_DATABASE_PATH',
            'nativephp-internal.secret' => 'NATIVEPHP_SECRET',
            'nativephp-internal.api_url' => 'NATIVEPHP_API_URL',
        ];
        $runtimeConfig = ['nativephp-internal.running' => true];
        $missingRuntimeValues = [];

        foreach ($requiredRuntimeValues as $configKey => $environmentVariable) {
            $runtimeValue = env($environmentVariable);

            if (! is_string($runtimeValue) || trim($runtimeValue) === '') {
                $missingRuntimeValues[] = $environmentVariable;

                continue;
            }

            $runtimeConfig[$configKey] = $runtimeValue;
        }

        if ($missingRuntimeValues !== []) {
            throw new \RuntimeException(
                'Missing required NativePHP runtime values: '.implode(', ', $missingRuntimeValues),
            );
        }

        config()->set($runtimeConfig);
    }

    /**
     * Re-register Sentry's reportable on whichever exception handler is active.
     *
     * NativePHP replaces Laravel's handler singleton with its own Handler in
     * NativeServiceProvider::packageRegistered(). That swap happens during the
     * register phase, before boot(). By the time we reach boot(), the active
     * handler is NativePHP's, and any reportable from bootstrap/app.php is lost.
     * Re-registering here ensures Sentry captures errors in all environments.
     */
    protected function configureSentry(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (method_exists($handler, 'reportable')) {
            $handler->reportable(function (\Throwable $e) {
                Integration::captureUnhandledException($e);
            });
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
