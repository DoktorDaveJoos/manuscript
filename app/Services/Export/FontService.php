<?php

namespace App\Services\Export;

class FontService
{
    private ?bool $available = null;

    /**
     * Get the path to the regular Literata font file.
     */
    public function regularFontPath(): string
    {
        return resource_path('fonts/Literata.ttf');
    }

    /**
     * Get the path to the italic Literata font file.
     */
    public function italicFontPath(): string
    {
        return resource_path('fonts/Literata-Italic.ttf');
    }

    /**
     * Check if font files are available.
     */
    public function fontsAvailable(): bool
    {
        return $this->available ??= file_exists($this->regularFontPath()) && file_exists($this->italicFontPath());
    }

    /**
     * Get mPDF font configuration for Literata.
     *
     * @return array<string, array<string, mixed>>
     */
    public function mPdfFontData(): array
    {
        return [
            'literata' => [
                'R' => 'Literata.ttf',
                'I' => 'Literata-Italic.ttf',
            ],
        ];
    }

    /**
     * Get CSS @font-face declarations for EPUB embedding.
     */
    public function epubFontFaceCss(): string
    {
        $css = '';

        if ($this->fontsAvailable()) {
            $css .= <<<'CSS'
            @font-face {
                font-family: "Literata";
                src: url("../Fonts/Literata.ttf");
                font-weight: 100 900;
                font-style: normal;
            }
            @font-face {
                font-family: "Literata";
                src: url("../Fonts/Literata-Italic.ttf");
                font-weight: 100 900;
                font-style: italic;
            }
            CSS;
        }

        return $css;
    }
}
