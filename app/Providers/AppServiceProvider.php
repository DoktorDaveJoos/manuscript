<?php

namespace App\Providers;

use App\Console\Commands\OptimizeCommand;
use App\Database\SqliteVecConnector;
use App\Listeners\RecordAiTokenUsage;
use App\Models\Act;
use App\Models\Beat;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Observers\BoardChangeObserver;
use App\Services\DatabaseRepairService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Sentry\Laravel\Integration;
use Sentry\Severity;
use Sentry\State\Scope;

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

        // After all service providers boot (including NativePHP's database
        // rewrite), run pending migrations and attempt data recovery if the
        // connector detected a corrupt database.
        $this->app->booted(function () {
            $this->ensureDatabaseSchema();
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

    /**
     * Run pending migrations and attempt data recovery after corruption repair.
     *
     * Fires from the booted() callback — by this point NativePHP has already
     * rewritten the default database connection, so migrate targets the correct
     * database (nativephp.sqlite in production, database.sqlite in dev).
     */
    protected function ensureDatabaseSchema(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        // NativePHP's bundled PHP runs as cli-server so runningInConsole() is
        // false there; any real CLI context must leave migrations to the dev.
        if ($this->app->runningInConsole()) {
            return;
        }

        try {
            Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        } catch (\Throwable $e) {
            Log::error('DatabaseIntegrity: migration failed during boot.', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // If the connector repaired a corrupt database, try to recover data
        // from the backup now that the fresh database has its schema.
        if ($this->app->bound('database.repaired')) {
            $repairInfo = $this->app->make('database.repaired');
            $backupPath = $repairInfo['backup'] ?? null;

            if ($backupPath && file_exists($backupPath)) {
                $service = $this->app->make(DatabaseRepairService::class);
                $result = $service->recoverData($backupPath);

                // Update the repair info with recovery results so middleware
                // can pass it to the frontend.
                $repairInfo = array_merge($repairInfo, $result);
                $this->app->instance('database.repaired', $repairInfo);
            }

            @unlink(SqliteVecConnector::markerPath());

            $this->reportRepairToSentry($repairInfo);
        }
    }

    /**
     * Emit a Sentry event so operators see auto-repair activity — without this,
     * silent "Ok" recoveries never reach monitoring and a growing rate of
     * corrupt-DB events across the user base could go unnoticed.
     */
    protected function reportRepairToSentry(array $repairInfo): void
    {
        if (! $this->app->bound('sentry')) {
            return;
        }

        \Sentry\withScope(function (Scope $scope) use ($repairInfo) {
            $recovered = $repairInfo['recovered'] ?? [];
            $failed = $repairInfo['failed'] ?? [];

            $scope->setTag('database_integrity', $failed === [] ? 'repaired' : 'repaired_partial');
            $scope->setContext('repair', [
                'backup' => $repairInfo['backup'] ?? null,
                'trigger' => $repairInfo['trigger'] ?? null,
                'recovered_tables' => $recovered,
                'failed_tables' => $failed,
                'recovered_count' => count($recovered),
                'failed_count' => count($failed),
            ]);

            \Sentry\captureMessage(
                'Database corruption detected and auto-repaired',
                Severity::error(),
            );
        });
    }
}
