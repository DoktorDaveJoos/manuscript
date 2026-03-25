<?php

namespace App\Services\Export;

use App\Enums\FontPairing;

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

    /**
     * Get the full path to a font file by filename.
     */
    public function fontPathForFile(string $filename): string
    {
        return resource_path("fonts/{$filename}");
    }

    /**
     * Check if all font files for a pairing are available.
     */
    public function fontsAvailableForPairing(FontPairing $pairing): bool
    {
        foreach ($pairing->fontFiles() as $font) {
            if (! file_exists($this->fontPathForFile($font['file']))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get mPDF font configuration for a given font pairing.
     *
     * @return array<string, array<string, string>>
     */
    public function mPdfFontDataForPairing(FontPairing $pairing): array
    {
        $data = [];
        $grouped = [];

        foreach ($pairing->fontFiles() as $font) {
            $key = strtolower(str_replace(' ', '', $font['family']));
            $grouped[$key][$font['weight']][$font['style']] = $font['file'];
        }

        foreach ($grouped as $key => $weights) {
            $entry = [];
            $entry['R'] = $weights['normal']['normal'] ?? '';
            if (isset($weights['normal']['italic'])) {
                $entry['I'] = $weights['normal']['italic'];
            }
            if (isset($weights['bold']['normal'])) {
                $entry['B'] = $weights['bold']['normal'];
            }
            if (isset($weights['bold']['italic'])) {
                $entry['BI'] = $weights['bold']['italic'];
            }
            $data[$key] = $entry;
        }

        return $data;
    }

    /**
     * Get font files for EPUB embedding for a given pairing.
     *
     * @return array<int, array{path: string, filename: string}>
     */
    public function epubFontFilesForPairing(FontPairing $pairing): array
    {
        $files = [];

        foreach ($pairing->fontFiles() as $font) {
            $path = $this->fontPathForFile($font['file']);
            if (file_exists($path)) {
                $files[] = [
                    'path' => $path,
                    'filename' => $font['file'],
                ];
            }
        }

        return $files;
    }

    /**
     * Get CSS @font-face declarations for EPUB embedding for a given pairing.
     */
    public function epubFontFaceCssForPairing(FontPairing $pairing): string
    {
        $css = '';

        foreach ($pairing->fontFiles() as $font) {
            $css .= "@font-face {\n";
            $css .= "    font-family: '{$font['family']}';\n";
            $css .= "    src: url('Fonts/{$font['file']}');\n";
            $css .= "    font-weight: {$font['weight']};\n";
            $css .= "    font-style: {$font['style']};\n";
            $css .= "}\n\n";
        }

        return $css;
    }

    /**
     * Get the font directories for mPDF.
     *
     * @return array<int, string>
     */
    public function mPdfFontDirectories(): array
    {
        return [resource_path('fonts/')];
    }
}
