<?php

use Tests\TestCase;

uses(TestCase::class);

/**
 * Regression test for the stale-routes-v7.php cleanup that ships alongside the
 * OptimizeCommand override (see OptimizeCommandTest). Users upgrading from
 * v0.4.7/v0.4.8 may have a routes-v7.php left in the writable bootstrap cache;
 * without this cleanup, Laravel 13's withRouting loads it as CompiledRouteCollection
 * and pins users to out-of-date routes.
 */
it('deletes a given route cache file when it exists', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'routes-v7-').'.php';
    file_put_contents($tmp, '<?php // stale route cache');

    expect(is_file($tmp))->toBeTrue();

    clear_stale_route_cache($tmp);

    expect(is_file($tmp))->toBeFalse();
});

it('is a no-op when the target file does not exist', function () {
    $tmp = sys_get_temp_dir().'/nonexistent-routes-'.uniqid().'.php';

    expect(is_file($tmp))->toBeFalse();

    clear_stale_route_cache($tmp);

    expect(is_file($tmp))->toBeFalse();
});

it('resolves the cache path from APP_ROUTES_CACHE when no argument is passed', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'routes-v7-env-').'.php';
    file_put_contents($tmp, '<?php // stale route cache');

    $_SERVER['APP_ROUTES_CACHE'] = $tmp;

    try {
        clear_stale_route_cache();
        expect(is_file($tmp))->toBeFalse();
    } finally {
        unset($_SERVER['APP_ROUTES_CACHE']);
        @unlink($tmp);
    }
});
