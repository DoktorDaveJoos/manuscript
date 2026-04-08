<?php

test('loading route renders and redirects to root', function () {
    $response = $this->get(route('loading'));

    $response->assertSuccessful();
    // The loading page redirects client-side via window.location.replace('/').
    $response->assertSee("window.location.replace('/')", false);
});

test('url helper produces absolute url for loading route', function () {
    // NativePHP forwards window URLs straight to Electron's loadURL(), which
    // rejects bare paths. Asserts the helper emits a full scheme+host URL so
    // NativeAppServiceProvider::boot() can't regress to a relative path.
    $absolute = url('/loading');

    expect($absolute)
        ->toStartWith('http')
        ->toEndWith('/loading');
});
