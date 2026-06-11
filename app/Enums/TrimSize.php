<?php

namespace App\Enums;

enum TrimSize: string
{
    case MassMarket = '4.25x6.87';
    case Novel13x19 = '13x19cm';
    case Pocket = '5x8';
    case Digest = '5.25x8';
    case SmallTrade = '5.5x8.5';
    case A5 = 'a5';
    case UsTrade = '6x9';
    case Royal = '7x10';
    case A4 = 'a4';
    case Manuscript = '8.5x11';

    /**
     * Imperial label, shown to English (inch-based) locales.
     */
    public function label(): string
    {
        return match ($this) {
            self::MassMarket => '4.25" × 6.87"',
            self::Novel13x19 => '5.1" × 7.5"',
            self::Pocket => '5" × 8"',
            self::Digest => '5.25" × 8"',
            self::SmallTrade => '5.5" × 8.5"',
            self::A5 => 'A5 (5.8" × 8.3")',
            self::UsTrade => '6" × 9"',
            self::Royal => '7" × 10"',
            self::A4 => 'A4 (8.3" × 11.7")',
            self::Manuscript => '8.5" × 11"',
        };
    }

    /**
     * Metric label (cm, comma decimal), shown to metric locales (de, es).
     */
    public function metricLabel(): string
    {
        return match ($this) {
            self::MassMarket => '10,8 × 17,5 cm',
            self::Novel13x19 => '13 × 19 cm',
            self::Pocket => '12,7 × 20,3 cm',
            self::Digest => '13,3 × 20,3 cm',
            self::SmallTrade => '14 × 21,6 cm',
            self::A5 => 'A5 (14,8 × 21 cm)',
            self::UsTrade => '15,2 × 22,9 cm',
            self::Royal => '17,8 × 25,4 cm',
            self::A4 => 'A4 (21 × 29,7 cm)',
            self::Manuscript => '21,6 × 27,9 cm',
        };
    }

    /**
     * Page dimensions in millimetres. Imperial presets carry the exact
     * inch-derived values (6×9″ = 152.4×228.6) — printers match the PDF page
     * size against the chosen trim, so rounding to whole millimetres puts
     * every export up to 0.5 mm off the template.
     *
     * @return array{width: float, height: float}
     */
    public function dimensions(): array
    {
        return match ($this) {
            self::MassMarket => ['width' => 107.95, 'height' => 174.5],
            self::Novel13x19 => ['width' => 130.0, 'height' => 190.0],
            self::Pocket => ['width' => 127.0, 'height' => 203.2],
            self::Digest => ['width' => 133.35, 'height' => 203.2],
            self::SmallTrade => ['width' => 139.7, 'height' => 215.9],
            self::A5 => ['width' => 148.0, 'height' => 210.0],
            self::UsTrade => ['width' => 152.4, 'height' => 228.6],
            self::Royal => ['width' => 177.8, 'height' => 254.0],
            self::A4 => ['width' => 210.0, 'height' => 297.0],
            self::Manuscript => ['width' => 215.9, 'height' => 279.4],
        };
    }

    /**
     * Page margins in millimetres, following trade-book conventions: the
     * gutter is widest (binding eats into it), the bottom outweighs the top
     * (folio + optical centring), and the outer edge keeps enough air that
     * thumbs never cover text. Roughly: outer ≈ 0.6–0.75″, gutter ≈ outer
     * + 5 mm, scaled with the trim.
     *
     * @return array{top: int, bottom: int, outer: int, gutter: int}
     */
    public function margins(): array
    {
        return match ($this) {
            self::MassMarket => ['top' => 15, 'bottom' => 16, 'outer' => 13, 'gutter' => 17],
            self::Novel13x19 => ['top' => 18, 'bottom' => 19, 'outer' => 15, 'gutter' => 20],
            self::Pocket => ['top' => 18, 'bottom' => 19, 'outer' => 15, 'gutter' => 20],
            self::Digest => ['top' => 18, 'bottom' => 20, 'outer' => 16, 'gutter' => 21],
            self::SmallTrade => ['top' => 20, 'bottom' => 22, 'outer' => 17, 'gutter' => 22],
            self::A5 => ['top' => 20, 'bottom' => 22, 'outer' => 17, 'gutter' => 22],
            self::UsTrade => ['top' => 21, 'bottom' => 23, 'outer' => 19, 'gutter' => 24],
            self::Royal => ['top' => 23, 'bottom' => 25, 'outer' => 21, 'gutter' => 26],
            self::A4 => ['top' => 26, 'bottom' => 28, 'outer' => 23, 'gutter' => 28],
            self::Manuscript => ['top' => 25, 'bottom' => 25, 'outer' => 25, 'gutter' => 25],
        };
    }

    /**
     * Sensible default page margins (mm) for an arbitrary custom trim size,
     * scaled from the page dimensions and roughly matching the presets.
     *
     * @return array{top: int, bottom: int, outer: int, gutter: int}
     */
    public static function defaultMarginsFor(float $width, float $height): array
    {
        $outer = (int) round($width * 0.125);

        return [
            'top' => (int) round($height * 0.092),
            'bottom' => (int) round($height * 0.1),
            'outer' => $outer,
            'gutter' => $outer + 5,
        ];
    }
}
