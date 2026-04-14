<?php

namespace App\Providers;

use App\Database\SqliteVecConnector;
use App\Listeners\RecordAiTokenUsage;
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

        Event::listen(AgentPrompted::class, RecordAiTokenUsage::class);
        Event::listen(AgentStreamed::class, RecordAiTokenUsage::class);
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

        if ($this->app->runningUnitTests()) {
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
                $this->app->instance('database.repaired', array_merge($repairInfo, $result));
            }
        }
    }
}
