<?php

namespace App\Enums;

enum FontPairing: string
{
    case ClassicSerif = 'classic-serif';
    case ModernMixed = 'modern-mixed';
    case ElegantSerif = 'elegant-serif';

    public function label(): string
    {
        return match ($this) {
            self::ClassicSerif => 'Classic Serif',
            self::ModernMixed => 'Modern Mixed',
            self::ElegantSerif => 'Elegant Serif',
        };
    }

    public function headingFont(): string
    {
        return match ($this) {
            self::ClassicSerif => 'Crimson Pro',
            self::ModernMixed => 'Source Sans 3',
            self::ElegantSerif => 'Cormorant Garamond',
        };
    }

    public function bodyFont(): string
    {
        return match ($this) {
            self::ClassicSerif => 'Crimson Pro',
            self::ModernMixed => 'Source Serif 4',
            self::ElegantSerif => 'Crimson Pro',
        };
    }

    public function headingFontFamily(): string
    {
        return match ($this) {
            self::ClassicSerif => "'Crimson Pro', Georgia, serif",
            self::ModernMixed => "'Source Sans 3', 'Helvetica Neue', sans-serif",
            self::ElegantSerif => "'Cormorant Garamond', Georgia, serif",
        };
    }

    public function bodyFontFamily(): string
    {
        return match ($this) {
            self::ClassicSerif => "'Crimson Pro', Georgia, serif",
            self::ModernMixed => "'Source Serif 4', Georgia, serif",
            self::ElegantSerif => "'Crimson Pro', Georgia, serif",
        };
    }

    /**
     * Returns array of font file definitions needed for this pairing.
     *
     * @return array<int, array{family: string, file: string, weight: string, style: string}>
     */
    public function fontFiles(): array
    {
        return match ($this) {
            self::ClassicSerif => [
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-Regular.ttf', 'weight' => 'normal', 'style' => 'normal'],
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-Italic.ttf', 'weight' => 'normal', 'style' => 'italic'],
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-Bold.ttf', 'weight' => 'bold', 'style' => 'normal'],
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-BoldItalic.ttf', 'weight' => 'bold', 'style' => 'italic'],
            ],
            self::ModernMixed => [
                ['family' => 'Source Sans 3', 'file' => 'SourceSans3-Regular.ttf', 'weight' => 'normal', 'style' => 'normal'],
                ['family' => 'Source Sans 3', 'file' => 'SourceSans3-Bold.ttf', 'weight' => 'bold', 'style' => 'normal'],
                ['family' => 'Source Serif 4', 'file' => 'SourceSerif4-Regular.ttf', 'weight' => 'normal', 'style' => 'normal'],
                ['family' => 'Source Serif 4', 'file' => 'SourceSerif4-Italic.ttf', 'weight' => 'normal', 'style' => 'italic'],
                ['family' => 'Source Serif 4', 'file' => 'SourceSerif4-Bold.ttf', 'weight' => 'bold', 'style' => 'normal'],
                ['family' => 'Source Serif 4', 'file' => 'SourceSerif4-BoldItalic.ttf', 'weight' => 'bold', 'style' => 'italic'],
            ],
            self::ElegantSerif => [
                ['family' => 'Cormorant Garamond', 'file' => 'CormorantGaramond-Regular.ttf', 'weight' => 'normal', 'style' => 'normal'],
                ['family' => 'Cormorant Garamond', 'file' => 'CormorantGaramond-Italic.ttf', 'weight' => 'normal', 'style' => 'italic'],
                ['family' => 'Cormorant Garamond', 'file' => 'CormorantGaramond-Bold.ttf', 'weight' => 'bold', 'style' => 'normal'],
                ['family' => 'Cormorant Garamond', 'file' => 'CormorantGaramond-BoldItalic.ttf', 'weight' => 'bold', 'style' => 'italic'],
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-Regular.ttf', 'weight' => 'normal', 'style' => 'normal'],
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-Italic.ttf', 'weight' => 'normal', 'style' => 'italic'],
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-Bold.ttf', 'weight' => 'bold', 'style' => 'normal'],
                ['family' => 'Crimson Pro', 'file' => 'CrimsonPro-BoldItalic.ttf', 'weight' => 'bold', 'style' => 'italic'],
            ],
        };
    }
}
