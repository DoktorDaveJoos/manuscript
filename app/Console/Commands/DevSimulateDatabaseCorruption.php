<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dev:simulate-db-corruption
    {--pattern=truncate : Corruption pattern — truncate | zero-page | flip-header}
    {--path= : Override the SQLite file to damage (defaults to the nativephp DB)}
    {--force : Skip the confirmation prompt}')]
#[Description('Intentionally damage a SQLite DB to exercise the auto-repair flow. For DEV USE ONLY.')]
class DevSimulateDatabaseCorruption extends Command
{
    /**
     * @var array<string, string>
     */
    private const PATTERN_LABELS = [
        'truncate' => 'Truncate file to 40% (partial-write crash)',
        'zero-page' => 'Zero a middle data page (single sector failure)',
        'flip-header' => 'Flip a byte in the SQLite magic header (disk bit-rot)',
    ];

    public function handle(): int
    {
        $pattern = (string) $this->option('pattern');

        if (! isset(self::PATTERN_LABELS[$pattern])) {
            $this->error("Unknown pattern [{$pattern}]. Use one of: ".implode(', ', array_keys(self::PATTERN_LABELS)));

            return self::FAILURE;
        }

        $path = (string) ($this->option('path') ?: database_path('nativephp.sqlite'));

        if (! file_exists($path)) {
            $this->error("Database file not found at [{$path}].");

            return self::FAILURE;
        }

        $this->warn('This will DAMAGE the database file. Make sure it is not open in NativePHP.');
        $this->line("  Target: {$path}");
        $this->line('  Pattern: '.self::PATTERN_LABELS[$pattern]);

        if (! $this->option('force') && ! $this->confirm('Proceed?', false)) {
            return self::FAILURE;
        }

        // Keep an untouched copy so the developer can restore manually if they
        // want to re-run the experiment.
        $safetyCopy = $path.'.pre-corruption.'.date('Y-m-d_His');
        copy($path, $safetyCopy);

        $this->applyPattern($path, $pattern);

        $this->newLine();
        $this->info('Corruption applied.');
        $this->line('  Safety copy: '.$safetyCopy);
        $this->line('  Relaunch NativePHP — the loading screen should show "Restoring your data" within a few seconds,');
        $this->line('  then a "Database Repaired" dialog once recovery completes.');

        return self::SUCCESS;
    }

    private function applyPattern(string $path, string $pattern): void
    {
        $data = (string) file_get_contents($path);

        $mutated = match ($pattern) {
            'truncate' => substr($data, 0, (int) (strlen($data) * 0.4)),
            'zero-page' => $this->zeroMiddlePage($data),
            'flip-header' => $this->flipHeaderByte($data),
        };

        file_put_contents($path, $mutated);
    }

    private function zeroMiddlePage(string $data): string
    {
        $pageSize = 4096;
        $pages = intdiv(strlen($data), $pageSize);

        if ($pages < 4) {
            return substr($data, 0, (int) (strlen($data) * 0.5));
        }

        $target = intdiv($pages, 2);

        return substr_replace($data, str_repeat("\0", $pageSize), $target * $pageSize, $pageSize);
    }

    private function flipHeaderByte(string $data): string
    {
        $data[5] = chr(ord($data[5]) ^ 0xFF);

        return $data;
    }
}
