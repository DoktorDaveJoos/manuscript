<?php

namespace App\Services;

use App\Database\SqliteVecConnector;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Sentry\Severity;
use Sentry\State\Scope;

/**
 * Boot-time database guardian: runs pending migrations where the request
 * path is responsible for them, and finishes corruption repairs by restoring
 * user data from the backup the connector set aside.
 *
 * Repair detection is marker-driven, not container-driven: when corruption is
 * caught by the launch-time CLI `migrate --force` (the common production
 * case), the `database.repaired` binding dies with that process. The
 * `.repairing` marker next to the DB file — which carries the backup path —
 * is the only cross-process handle the next web request has.
 */
class DatabaseStartupService
{
    public function __construct(
        private Application $app,
        private bool $runningInConsole,
    ) {}

    /**
     * Run pending migrations and attempt data recovery after corruption repair.
     *
     * Fires from AppServiceProvider's booted() callback — by this point
     * NativePHP has already rewritten the default connection to `nativephp`,
     * so the guard must key off the DRIVER, never the connection name.
     */
    public function ensureSchema(): void
    {
        $default = config('database.default');

        if (config("database.connections.{$default}.driver") !== 'sqlite') {
            return;
        }

        // NativePHP's bundled PHP serves requests via cli-server, where
        // runningInConsole() is false; real CLI contexts (artisan, tinker,
        // the launch-time migrate) must not trigger boot migrations here.
        if ($this->runningInConsole) {
            return;
        }

        $repairInfo = $this->pendingRepairInfo();

        // Under NativePHP the Electron main process runs `migrate --force`
        // once per launch, so the request path only migrates when finishing a
        // repair (a web-detected repair leaves a schemaless fresh DB behind).
        // Outside NativePHP (browser dev) this is the only migration path.
        if (! config('nativephp-internal.running') || $repairInfo !== null) {
            try {
                Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
            } catch (\Throwable $e) {
                Log::error('DatabaseIntegrity: migration failed during boot.', [
                    'error' => $e->getMessage(),
                ]);

                return;
            }
        }

        if ($repairInfo === null) {
            return;
        }

        $backupPath = $repairInfo['backup'] ?? null;

        if ($backupPath && file_exists($backupPath)) {
            $result = $this->app->make(DatabaseRepairService::class)->recoverData($backupPath);
            $repairInfo = array_merge($repairInfo, $result);
        }

        // Bind (or refresh) the repair info so HandleInertiaRequests can
        // surface the recovery toast on this request.
        $this->app->instance('database.repaired', $repairInfo);

        @unlink(SqliteVecConnector::markerPath());

        $this->reportRepairToSentry($repairInfo);
    }

    /**
     * Repair info from this process's connector binding, or from the marker a
     * previous (CLI) process left behind. Null when no repair is pending.
     *
     * @return array{backup: ?string, trigger: ?string, recovered: list<string>, failed: list<string>}|null
     */
    private function pendingRepairInfo(): ?array
    {
        if ($this->app->bound('database.repaired')) {
            return (array) $this->app->make('database.repaired');
        }

        $marker = SqliteVecConnector::markerPath();

        if (! file_exists($marker)) {
            return null;
        }

        $payload = json_decode((string) @file_get_contents($marker), true) ?: [];

        return [
            'backup' => $payload['backup'] ?? null,
            'trigger' => $payload['trigger'] ?? null,
            'recovered' => [],
            'failed' => [],
        ];
    }

    /**
     * Emit a Sentry event so operators see auto-repair activity — without
     * this, silent "Ok" recoveries never reach monitoring and a growing rate
     * of corrupt-DB events across the user base could go unnoticed.
     *
     * @param  array{backup: ?string, trigger: ?string, recovered: list<string>, failed: list<string>}  $repairInfo
     */
    private function reportRepairToSentry(array $repairInfo): void
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
