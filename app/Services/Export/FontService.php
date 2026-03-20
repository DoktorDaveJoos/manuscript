<?php

namespace App\Services\Export;

class FontService
{
    private ?bool $available = null;

    /**
     * Get the path to the regular Spectral font file.
     */
    public function regularFontPath(): string
    {
        return resource_path('fonts/Spectral.ttf');
    }

    /**
     * Get the path to the italic Spectral font file.
     */
    public function italicFontPath(): string
    {
        return resource_path('fonts/Spectral-Italic.ttf');
    }

    /**
     * Check if font files are available.
     */
    public function fontsAvailable(): bool
    {
        return $this->available ??= file_exists($this->regularFontPath()) && file_exists($this->italicFontPath());
    }

    /**
     * Get mPDF font configuration for Spectral.
     *
     * @return array<string, array<string, mixed>>
     */
    public function mPdfFontData(): array
    {
        return [
            'spectral' => [
                'R' => 'Spectral.ttf',
                'I' => 'Spectral-Italic.ttf',
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
                font-family: "Spectral";
                src: url("../Fonts/Spectral.ttf");
                font-weight: 100 900;
                font-style: normal;
            }
            @font-face {
                font-family: "Spectral";
                src: url("../Fonts/Spectral-Italic.ttf");
                font-weight: 100 900;
                font-style: italic;
            }
            CSS;
        }

        return $css;
    }
}
