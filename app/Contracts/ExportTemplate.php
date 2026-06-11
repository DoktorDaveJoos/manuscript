<?php

namespace App\Contracts;

use App\Enums\FontPairing;
use App\Enums\SceneBreakStyle;

interface ExportTemplate
{
    public function slug(): string;

    public function name(): string;

    /**
     * @return array<string, mixed>
     */
    public function designTokens(): array;

    public function defaultFontPairing(): FontPairing;

    public function defaultSceneBreakStyle(): SceneBreakStyle;

    public function defaultDropCaps(): bool;

    public function pdfCss(int $fontSize, ?FontPairing $fontPairing = null): string;

    public function ebookPreviewCss(int $fontSize, ?FontPairing $fontPairing = null): string;

    public function epubCss(string $fontFaceCss, ?FontPairing $fontPairing = null): string;

    public function sceneBreakCss(): string;

    /**
     * Drop-cap CSS for mPDF rendering. mPDF cannot float inline elements, so
     * templates set a raised cap: an oversized initial sitting on the first
     * baseline, with `line-stacking-strategy: block-line-height` keeping the
     * opening line's leading even.
     */
    public function dropCapCss(?FontPairing $fontPairing = null): string;

    /**
     * Drop-cap CSS for EPUB rendering, where reader engines support a true
     * floated drop cap.
     */
    public function epubDropCapCss(?FontPairing $fontPairing = null): string;

    /**
     * HTML markup for the chapter opening: the number label, plus the title when $includeTitle is true.
     */
    public function chapterHeaderHtml(int $index, string $title, string $locale = 'en', bool $includeTitle = true): string;
}
