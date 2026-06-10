<?php

namespace App\Providers;

use App\Console\Commands\OptimizeCommand;
use App\Database\ResilientMigrationRepository;
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

        // The startup service needs to distinguish real CLI contexts from
        // NativePHP's cli-server requests — capture the flag at build time.
        $this->app->singleton(
            DatabaseStartupService::class,
            fn ($app) => new DatabaseStartupService($app, $app->runningInConsole()),
        );

        // After all service providers boot (including NativePHP's database
        // rewrite), run pending migrations and attempt data recovery if a
        // corruption repair is pending — this process's connector may have
        // detected it, or a previous launch-time CLI migrate left a marker.
        $this->app->booted(function () {
            $this->app->make(DatabaseStartupService::class)->ensureSchema();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSentry();
        $this->healStaleNativePhpSecret();

        Event::listen(AgentPrompted::class, RecordAiTokenUsage::class);
        Event::listen(AgentStreamed::class, RecordAiTokenUsage::class);

        PlotPoint::observe(BoardChangeObserver::class);
        Beat::observe(BoardChangeObserver::class);
        Storyline::observe(BoardChangeObserver::class);
        Act::observe(BoardChangeObserver::class);
    }

    /**
     * Reconcile a stale NativePHP secret cache at request time.
     *
     * Electron generates a fresh `NATIVEPHP_SECRET` per launch and hands it to
     * PHP via the process env. NativePHP regenerates the config cache on each
     * boot via `artisan optimize`, but that call is non-fatal in vendor code
     * (vendor/nativephp/desktop/resources/electron/electron-plugin/src/server/php.ts:437).
     * If optimize ever fails, the server boots against the previous launch's
     * cached secret and `PreventRegularBrowserAccess` 403s every
     * `/_native/api/events` call until the user relaunches — see Sentry issue
     * 113317190. Reading env() directly sidesteps the cache.
     */
    protected function healStaleNativePhpSecret(): void
    {
        if (! config('nativephp-internal.running')) {
            return;
        }

        $runtimeSecret = env('NATIVEPHP_SECRET');

        if ($runtimeSecret === null || $runtimeSecret === '') {
            return;
        }

        if (config('nativephp-internal.secret') === $runtimeSecret) {
            return;
        }

        config()->set('nativephp-internal.secret', $runtimeSecret);
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
