<?php

use App\Services\Export\Templates\ClassicTemplate;
use App\Services\Export\Templates\ElegantTemplate;
use App\Services\Export\Templates\ModernTemplate;

/**
 * Regression: mPDF ignores CSS Level 3 `break-before: page` on block elements
 * unless the legacy `page-break-before: always` is also present. Without it,
 * front/back matter sections (dedication, epigraph, TOC, acknowledgments, etc.)
 * share pages instead of each starting on their own page.
 */
it('emits legacy page-break-before on matter-section for every template', function (string $class) {
    $template = new $class;
    $css = $template->pdfCss(11);

    expect($css)->toMatch('/\.matter-section\s*\{[^}]*page-break-before:\s*always/s');
    expect($css)->toMatch('/\.matter-section\s*\{[^}]*break-before:\s*page/s');
})->with([
    ClassicTemplate::class,
    ModernTemplate::class,
    ElegantTemplate::class,
]);
