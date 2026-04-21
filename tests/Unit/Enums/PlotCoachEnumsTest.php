<?php

use App\Enums\CoachingMode;
use App\Enums\PlotCoachSessionStatus;
use App\Enums\PlotCoachStage;

it('defines plot coach session status cases', function () {
    expect(PlotCoachSessionStatus::Active->value)->toBe('active');
    expect(PlotCoachSessionStatus::Archived->value)->toBe('archived');
    expect(PlotCoachSessionStatus::cases())->toHaveCount(2);
});

it('defines plot coach stage cases', function () {
    expect(PlotCoachStage::Intake->value)->toBe('intake');
    expect(PlotCoachStage::Structure->value)->toBe('structure');
    expect(PlotCoachStage::Plotting->value)->toBe('plotting');
    expect(PlotCoachStage::Entities->value)->toBe('entities');
    expect(PlotCoachStage::Refinement->value)->toBe('refinement');
    expect(PlotCoachStage::Complete->value)->toBe('complete');
    expect(PlotCoachStage::cases())->toHaveCount(6);
});

it('defines coaching mode cases', function () {
    expect(CoachingMode::Suggestive->value)->toBe('suggestive');
    expect(CoachingMode::Guided->value)->toBe('guided');
    expect(CoachingMode::cases())->toHaveCount(2);
});
