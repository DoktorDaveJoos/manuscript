<?php

namespace Tests;

use App\Models\AppSetting;
use App\Models\License;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset per-request static caches that would otherwise leak across tests.
        AppSetting::clearCache();
        License::clearActiveCache();
    }
}
