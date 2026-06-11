<?php

namespace App\Enums;

/**
 * Which trim edges the bleed extends past. Printers disagree: Lulu, BoD,
 * epubli and tredition want bleed on all four edges (sheet = trim + 2×bleed
 * in both dimensions), while KDP and IngramSpark forbid bleed on the binding
 * edge (sheet = trim + 1×bleed wide, + 2×bleed tall).
 */
enum BleedMode: string
{
    case All = 'all';
    case Outer = 'outer';
}
