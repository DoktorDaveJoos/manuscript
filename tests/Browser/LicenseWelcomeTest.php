<?php

use App\Models\AppSetting;
use App\Models\License;
use App\Support\Trial;

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

it('starts the seven day trial from the welcome screen', function () {
    $page = visit('/license/welcome');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Or start your free 7-day trial')
        ->click('Or start your free 7-day trial')
        ->assertPathIs('/')
        ->assertSee('Manuscript');

    expect(Trial::isActive())->toBeTrue();
});

it('hides the trial option once the trial has expired', function () {
    AppSetting::set('trial_started_at', now()->subDays(8)->toIso8601String());

    $page = visit('/license/welcome');

    $page->assertNoJavaScriptErrors()
        ->assertDontSee('Or start your free 7-day trial')
        ->assertSee('Your free trial has ended');
});
