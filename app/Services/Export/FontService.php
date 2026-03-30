<?php

namespace App\Services\Export;

use App\Enums\FontPairing;

class FontService
{
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
