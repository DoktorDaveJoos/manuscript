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

    public function dropCapCss(): string;
}
