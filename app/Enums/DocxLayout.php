<?php

namespace App\Enums;

/**
 * Page layout for DOCX manuscript exports.
 *
 * Manuscript is the international submission standard (Letter-ish, Times New
 * Roman 12 pt, double-spaced, left-aligned). Normseite is the German agency /
 * editor standard: DIN A4, Times New Roman 12 pt, 1.5 line spacing, Blocksatz,
 * ~30 lines per page with a wide correction margin.
 */
enum DocxLayout: string
{
    case Manuscript = 'manuscript';
    case Normseite = 'normseite';
}
