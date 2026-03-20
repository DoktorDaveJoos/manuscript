<?php

namespace App\Services\Export;

class FontService
{
    private ?bool $available = null;

    /**
     * Get the path to the regular Crimson Pro font file.
     */
    public function regularFontPath(): string
    {
        return resource_path('fonts/CrimsonPro-Regular.ttf');
    }

    /**
     * Get the path to the italic Crimson Pro font file.
     */
    public function italicFontPath(): string
    {
        return resource_path('fonts/CrimsonPro-Italic.ttf');
    }

    /**
     * Get the path to the bold Crimson Pro font file.
     */
    public function boldFontPath(): string
    {
        return resource_path('fonts/CrimsonPro-Bold.ttf');
    }

    /**
     * Get the path to the bold italic Crimson Pro font file.
     */
    public function boldItalicFontPath(): string
    {
        return resource_path('fonts/CrimsonPro-BoldItalic.ttf');
    }

    /**
     * Check if font files are available.
     */
    public function fontsAvailable(): bool
    {
        return $this->available ??= file_exists($this->regularFontPath())
            && file_exists($this->italicFontPath())
            && file_exists($this->boldFontPath())
            && file_exists($this->boldItalicFontPath());
    }

    /**
     * Get mPDF font configuration for Crimson Pro.
     *
     * @return array<string, array<string, mixed>>
     */
    public function mPdfFontData(): array
    {
        return [
            'crimsonpro' => [
                'R' => 'CrimsonPro-Regular.ttf',
                'I' => 'CrimsonPro-Italic.ttf',
                'B' => 'CrimsonPro-Bold.ttf',
                'BI' => 'CrimsonPro-BoldItalic.ttf',
            ],
        ];
    }

    /**
     * Get font files for EPUB embedding: manifest ID => [path, filename].
     *
     * @return array<string, array{path: string, filename: string}>
     */
    public function epubFontFiles(): array
    {
        return [
            'font-regular' => ['path' => $this->regularFontPath(), 'filename' => 'CrimsonPro-Regular.ttf'],
            'font-italic' => ['path' => $this->italicFontPath(), 'filename' => 'CrimsonPro-Italic.ttf'],
            'font-bold' => ['path' => $this->boldFontPath(), 'filename' => 'CrimsonPro-Bold.ttf'],
            'font-bold-italic' => ['path' => $this->boldItalicFontPath(), 'filename' => 'CrimsonPro-BoldItalic.ttf'],
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
                font-family: "Crimson Pro";
                src: url("../Fonts/CrimsonPro-Regular.ttf");
                font-weight: normal;
                font-style: normal;
            }
            @font-face {
                font-family: "Crimson Pro";
                src: url("../Fonts/CrimsonPro-Italic.ttf");
                font-weight: normal;
                font-style: italic;
            }
            @font-face {
                font-family: "Crimson Pro";
                src: url("../Fonts/CrimsonPro-Bold.ttf");
                font-weight: bold;
                font-style: normal;
            }
            @font-face {
                font-family: "Crimson Pro";
                src: url("../Fonts/CrimsonPro-BoldItalic.ttf");
                font-weight: bold;
                font-style: italic;
            }
            CSS;
        }

        return $css;
    }
}
