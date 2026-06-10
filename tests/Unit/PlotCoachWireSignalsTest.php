<?php

use App\Ai\Support\PlotCoachWireSignals;
use Illuminate\Support\Str;

test('stripScaffolding removes a [system: ...] note whose body contains brackets', function () {
    $content = "[system: Undo failed: SQLSTATE[23000]: Integrity constraint violation. Nothing changed.]\n\nLet's keep going.";

    expect(PlotCoachWireSignals::stripScaffolding($content))->toBe("Let's keep going.");
});

test('stripScaffolding removes stacked notes', function () {
    $content = "[system: a [nested [deep]] note]\n[system: another]\nreal text";

    expect(PlotCoachWireSignals::stripScaffolding($content))->toBe('real text');
});

test('stripScaffolding hides bare wire signals', function () {
    expect(PlotCoachWireSignals::stripScaffolding('APPROVE:batch:'.Str::uuid()))->toBe('');
    expect(PlotCoachWireSignals::stripScaffolding('UNDO:last'))->toBe('');
});

test('stripScaffolding leaves plain user text untouched', function () {
    expect(PlotCoachWireSignals::stripScaffolding('plain user text'))->toBe('plain user text');
});

test('stripScaffolding treats an unterminated note as pure scaffolding', function () {
    expect(PlotCoachWireSignals::stripScaffolding('[system: unterminated'))->toBe('');
});
