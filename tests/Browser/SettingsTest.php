<?php

use App\Models\License;

it('renders settings page with all tabs', function () {
    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->assertSee('General')
        ->assertSee('Editor')
        ->assertSee('Print')
        ->assertSee('Account');
});

it('shows appearance section with theme options', function () {
    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->assertSee('Appearance')
        ->assertSee('Light')
        ->assertSee('Dark')
        ->assertSee('System');
});

it('shows license section for activating pro', function () {
    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->assertSee('License')
        ->assertSee('Activate');
});

it('shows active license status when pro is enabled', function () {
    License::factory()->create();

    $page = visit('/settings');

    $page->assertNoJavaScriptErrors()
        ->assertSee('License active')
        ->assertSee('Deactivate');
});
