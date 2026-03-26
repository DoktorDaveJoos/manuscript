<?php

use App\Enums\EditorialPersona;

it('has a Lektor case', function () {
    expect(EditorialPersona::Lektor->value)->toBe('lektor');
});

it('returns a label for each persona', function () {
    expect(EditorialPersona::Lektor->label())->toBe('Lektor');
});

it('returns persona instructions that include key honesty phrases', function () {
    $instructions = EditorialPersona::Lektor->instructions();

    expect($instructions)
        ->toContain('serve the work, not the author\'s ego')
        ->toContain('Do not inflate scores')
        ->toContain('compliment sandwich');
});

it('returns score calibration text', function () {
    $calibration = EditorialPersona::Lektor->scoreCalibration();

    expect($calibration)
        ->toContain('55-65')
        ->toContain('86-95');
});

it('returns severity definitions', function () {
    $severity = EditorialPersona::Lektor->severityDefinitions();

    expect($severity)
        ->toContain('critical')
        ->toContain('warning')
        ->toContain('suggestion');
});

it('returns anti-pattern rules', function () {
    $rules = EditorialPersona::Lektor->antiPatternRules();

    expect($rules)
        ->toContain('DO NOT')
        ->toContain('hedge');
});
