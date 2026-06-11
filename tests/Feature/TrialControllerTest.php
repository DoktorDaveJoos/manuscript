<?php

use App\Models\AppSetting;
use App\Models\License;
use App\Support\Trial;

beforeEach(fn () => clearLicense());

test('start begins a seven day trial without a license', function () {
    $this->postJson(route('trial.start'))
        ->assertSuccessful();

    expect(Trial::isActive())->toBeTrue()
        ->and(AppSetting::get('trial_started_at'))->not->toBeNull();
});

test('start is refused when a trial was already started', function () {
    $this->postJson(route('trial.start'))->assertSuccessful();

    $startedAt = AppSetting::get('trial_started_at');

    $this->postJson(route('trial.start'))
        ->assertUnprocessable();

    expect(AppSetting::get('trial_started_at'))->toBe($startedAt);
});

test('start is refused after the trial expired', function () {
    $this->postJson(route('trial.start'))->assertSuccessful();

    $this->travel(8)->days();

    expect(Trial::isActive())->toBeFalse();

    $this->postJson(route('trial.start'))->assertUnprocessable();

    expect(Trial::isActive())->toBeFalse();
});

test('start is refused when a license is already active', function () {
    License::factory()->create();

    $this->postJson(route('trial.start'))->assertUnprocessable();

    expect(Trial::hasStarted())->toBeFalse();
});

test('trial expires exactly seven days after start', function () {
    $start = now();

    $this->postJson(route('trial.start'))->assertSuccessful();

    $this->travelTo($start->copy()->addDays(7)->subMinute());
    expect(Trial::isActive())->toBeTrue();

    $this->travelTo($start->copy()->addDays(7));
    expect(Trial::isActive())->toBeFalse()
        ->and(Trial::hasExpired())->toBeTrue();
});

test('trial reports remaining days', function () {
    $this->postJson(route('trial.start'))->assertSuccessful();

    expect(Trial::daysRemaining())->toBe(7);

    $this->travel(3)->days();
    expect(Trial::daysRemaining())->toBe(4);

    $this->travel(10)->days();
    expect(Trial::daysRemaining())->toBe(0);
});
