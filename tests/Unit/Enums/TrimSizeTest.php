<?php

use App\Enums\TrimSize;

it('exposes the new metric formats with millimetre dimensions', function () {
    expect(TrimSize::A5->dimensions())->toBe(['width' => 148, 'height' => 210]);
    expect(TrimSize::A4->dimensions())->toBe(['width' => 210, 'height' => 297]);
    expect(TrimSize::Novel13x19->dimensions())->toBe(['width' => 130, 'height' => 190]);
    expect(TrimSize::Novel13x19->value)->toBe('13x19cm');
});

it('provides an imperial and metric label for every format', function () {
    foreach (TrimSize::cases() as $size) {
        expect($size->label())->toBeString()->not->toBeEmpty();
        expect($size->metricLabel())
            ->toBeString()
            ->not->toBeEmpty()
            ->toContain('cm');
    }
});

it('keeps imperial labels free of metric units', function () {
    foreach (TrimSize::cases() as $size) {
        expect($size->label())->not->toContain('cm');
    }
});

it('defines positive dimensions and complete margins for every format', function () {
    foreach (TrimSize::cases() as $size) {
        $dims = $size->dimensions();
        expect($dims['width'])->toBeGreaterThan(0);
        expect($dims['height'])->toBeGreaterThan(0);

        expect($size->margins())
            ->toHaveKeys(['top', 'bottom', 'outer', 'gutter']);
    }
});
