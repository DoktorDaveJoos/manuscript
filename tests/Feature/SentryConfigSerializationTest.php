<?php

/*
|--------------------------------------------------------------------------
| Sentry config serialization
|--------------------------------------------------------------------------
|
| Laravel's `config:cache` (run during the NativePHP packaged build) does
| `eval("return " . var_export($config, true) . ";")` on the entire config
| array. Any Closure in config crashes the eval with
| "Call to undefined method Closure::__set_state()" and breaks the publish
| pipeline. These tests guard the known landmines.
|
*/

test('config/sentry.php survives the config:cache round-trip', function () {
    $sentryConfig = config('sentry');

    $exported = var_export($sentryConfig, true);

    // Closures render as `\Closure::__set_state(array(...))` — valid PHP
    // syntax for eval but calls a method that doesn't exist, which is the
    // exact failure mode we saw in Sentry.
    expect($exported)->not->toContain('Closure::__set_state');

    // Full round-trip: if this throws, config:cache would too.
    $rehydrated = eval("return {$exported};");

    expect($rehydrated)->toBeArray();
});

test('sentry.before_send is a callable that survives serialization', function () {
    $beforeSend = config('sentry.before_send');

    expect(is_callable($beforeSend))->toBeTrue();

    $exported = var_export($beforeSend, true);
    $rehydrated = eval("return {$exported};");

    expect(is_callable($rehydrated))->toBeTrue();
});
