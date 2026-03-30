<?php

namespace App\Providers;

use App\Database\SqliteVecConnector;
use App\Listeners\RecordAiTokenUsage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('db.connector.sqlite', SqliteVecConnector::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSqlite();

        Event::listen(AgentPrompted::class, RecordAiTokenUsage::class);
        Event::listen(AgentStreamed::class, RecordAiTokenUsage::class);
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
     * Apply SQLite pragmas that the NativePHP runtime connection misses.
     *
     * The 'sqlite' config connection gets pragmas via config/database.php,
     * but NativePHP registers a 'nativephp' connection at runtime without
     * those keys. This covers synchronous, cache_size, mmap_size, and
     * temp_store on whichever connection is active.
     */
    protected function configureSqlite(): void
    {
        try {
            DB::statement('PRAGMA synchronous = NORMAL;');
            DB::statement('PRAGMA cache_size = -64000;');
            DB::statement('PRAGMA mmap_size = 268435456;');
            DB::statement('PRAGMA temp_store = MEMORY;');
        } catch (\Throwable) {
            // Pragmas are performance optimizations — safe to skip during
            // package:discover or other bootstrap-phase commands where the
            // database may not be available yet (e.g. CI builds).
        }
    }
}
