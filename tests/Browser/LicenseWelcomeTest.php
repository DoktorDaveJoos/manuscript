<?php

use App\Models\License;

beforeEach(fn () => clearLicense());

it('shows the welcome screen on cold start without a license', function () {
    $page = visit('/');

    // RequiresLicense middleware should redirect any non-license route to
    // the welcome screen.
    $page->assertNoJavaScriptErrors()
        ->assertPathIs('/license/welcome')
        ->assertSee('Welcome to Manuscript')
        ->assertSee('Activate');
});

it('rejects an empty license key', function () {
    $page = visit('/license/welcome');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Activate');

    // Submit button is disabled until input has content
    expect(License::isActive())->toBeFalse();
});

it('exposes a way to acquire a license', function () {
    $page = visit('/license/welcome');

    $page->assertNoJavaScriptErrors()
        ->assertSee('getmanuscript.app');
});
