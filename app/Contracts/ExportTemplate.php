<?php

namespace App\Contracts;

interface ExportTemplate
{
    public static function slug(): string;

    public static function name(): string;

    /**
     * @return array<string, mixed>
     */
    public function designTokens(): array;

    public function pdfCss(int $fontSize): string;

    public function ebookPreviewCss(int $fontSize): string;

    public function epubCss(string $fontFaceCss): string;
}
