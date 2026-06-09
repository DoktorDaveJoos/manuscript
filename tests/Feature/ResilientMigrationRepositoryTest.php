<?php

use App\Database\ResilientMigrationRepository;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/manuscript_migrepo_'.uniqid();
    mkdir($this->tempDir);
    $this->dbPath = $this->tempDir.'/repo.sqlite';
    touch($this->dbPath);

    config(['database.connections.migrepo' => [
        'driver' => 'sqlite',
        'database' => $this->dbPath,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]]);

    DB::purge('migrepo');
});

afterEach(function () {
    DB::purge('migrepo');

    foreach (glob($this->tempDir.'/*') as $f) {
        @unlink($f);
    }
    @rmdir($this->tempDir);
});

function migrationsTable(string $connection): void
{
    DB::connection($connection)->getSchemaBuilder()->create('migrations', function ($table) {
        $table->increments('id');
        $table->string('migration');
        $table->integer('batch');
    });
}

function resilientRepo(string $connection): ResilientMigrationRepository
{
    $repo = new ResilientMigrationRepository(app('db'), 'migrations');
    $repo->setSource($connection);

    return $repo;
}

// ---------------------------------------------------------------------------
// The bug: base Laravel repository aborts when the table already exists
// ---------------------------------------------------------------------------

test('base Laravel repository throws when the migrations table already exists', function () {
    // Reproduces Sentry 123909138: repositoryExists() under-reported the table
    // (Windows/WAL), so migrate:install re-ran CREATE TABLE on a table that
    // was actually present.
    migrationsTable('migrepo');

    $base = new DatabaseMigrationRepository(app('db'), 'migrations');
    $base->setSource('migrepo');

    expect(fn () => $base->createRepository())->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// The fix: resilient repository is idempotent
// ---------------------------------------------------------------------------

test('createRepository succeeds when the migrations table already exists', function () {
    migrationsTable('migrepo');

    $repo = resilientRepo('migrepo');

    $repo->createRepository();

    expect($repo->repositoryExists())->toBeTrue();
});

test('createRepository creates the migrations table on a fresh database', function () {
    $repo = resilientRepo('migrepo');

    expect($repo->repositoryExists())->toBeFalse();

    $repo->createRepository();

    expect($repo->repositoryExists())->toBeTrue();
});

test('createRepository rethrows failures that are not "already exists"', function () {
    // Point the connection at a directory so opening/creating the database
    // fails for an unrelated reason — that error must not be swallowed.
    config(['database.connections.badrepo' => [
        'driver' => 'sqlite',
        'database' => $this->tempDir,
        'prefix' => '',
    ]]);
    DB::purge('badrepo');

    $repo = resilientRepo('badrepo');

    expect(fn () => $repo->createRepository())->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// Error discrimination + container wiring
// ---------------------------------------------------------------------------

test('isTableAlreadyExistsError only matches "already exists" messages', function (string $message, bool $expected) {
    expect(ResilientMigrationRepository::isTableAlreadyExistsError(new RuntimeException($message)))
        ->toBe($expected);
})->with([
    'sqlite' => ['SQLSTATE[HY000]: General error: 1 table "migrations" already exists', true],
    'mysql' => ["SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'migrations' already exists", true],
    'unrelated lock' => ['database is locked', false],
    'no such table' => ['no such table: migrations', false],
]);

test('the container resolves the hardened migration repository', function () {
    expect(app('migration.repository'))->toBeInstanceOf(ResilientMigrationRepository::class);
});
