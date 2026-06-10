<?php

use App\Providers\NativeAppServiceProvider;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function () {
    File::deleteDirectory(storage_path('framework/cache/opcache'));
});

it('does not cap PHP execution time so queue daemons are bounded by Laravel timeouts only', function () {
    $ini = (new NativeAppServiceProvider)->phpIni();

    expect($ini)->toHaveKey('max_execution_time', '0');
});

it('adds a version-keyed opcache file cache in production', function () {
    app()['env'] = 'production';
    config()->set('nativephp.version', '9.9.9');

    $ini = (new NativeAppServiceProvider)->phpIni();

    $expectedDir = storage_path('framework/cache/opcache'.DIRECTORY_SEPARATOR.'9.9.9');

    expect($ini)->toHaveKey('opcache.file_cache', $expectedDir)
        ->and(is_dir($expectedDir))->toBeTrue();
});

it('prunes file caches left behind by previous app versions', function () {
    app()['env'] = 'production';
    config()->set('nativephp.version', '9.9.9');

    $staleDir = storage_path('framework/cache/opcache'.DIRECTORY_SEPARATOR.'9.9.8');
    File::ensureDirectoryExists($staleDir);

    (new NativeAppServiceProvider)->phpIni();

    expect(is_dir($staleDir))->toBeFalse()
        ->and(is_dir(storage_path('framework/cache/opcache'.DIRECTORY_SEPARATOR.'9.9.9')))->toBeTrue();
});

it('does not enable the opcache file cache outside production', function () {
    $ini = (new NativeAppServiceProvider)->phpIni();

    expect($ini)->not->toHaveKey('opcache.file_cache')
        ->and($ini)->not->toHaveKey('opcache.validate_timestamps');
});
