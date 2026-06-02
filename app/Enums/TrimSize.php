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
     * Page dimensions in millimetres.
     *
     * @return array{width: float, height: float}
     */
    public function dimensions(): array
    {
        return match ($this) {
            self::MassMarket => ['width' => 108, 'height' => 175],
            self::Novel13x19 => ['width' => 130, 'height' => 190],
            self::Pocket => ['width' => 127, 'height' => 203],
            self::Digest => ['width' => 133, 'height' => 203],
            self::SmallTrade => ['width' => 140, 'height' => 216],
            self::A5 => ['width' => 148, 'height' => 210],
            self::UsTrade => ['width' => 152, 'height' => 229],
            self::Royal => ['width' => 178, 'height' => 254],
            self::A4 => ['width' => 210, 'height' => 297],
            self::Manuscript => ['width' => 216, 'height' => 279],
        };
    }

    /**
     * Page margins in millimetres.
     *
     * @return array{top: int, bottom: int, outer: int, gutter: int}
     */
    public function margins(): array
    {
        return match ($this) {
            self::MassMarket => ['top' => 13, 'bottom' => 15, 'outer' => 11, 'gutter' => 16],
            self::Novel13x19 => ['top' => 16, 'bottom' => 18, 'outer' => 14, 'gutter' => 19],
            self::Pocket => ['top' => 16, 'bottom' => 18, 'outer' => 14, 'gutter' => 19],
            self::Digest => ['top' => 16, 'bottom' => 18, 'outer' => 14, 'gutter' => 20],
            self::SmallTrade => ['top' => 19, 'bottom' => 22, 'outer' => 16, 'gutter' => 22],
            self::A5 => ['top' => 18, 'bottom' => 20, 'outer' => 15, 'gutter' => 20],
            self::UsTrade => ['top' => 19, 'bottom' => 22, 'outer' => 16, 'gutter' => 22],
            self::Royal => ['top' => 22, 'bottom' => 25, 'outer' => 19, 'gutter' => 25],
            self::A4 => ['top' => 25, 'bottom' => 25, 'outer' => 22, 'gutter' => 25],
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
        $outer = (int) round($width * 0.1);

        return [
            'top' => (int) round($height * 0.085),
            'bottom' => (int) round($height * 0.095),
            'outer' => $outer,
            'gutter' => $outer + 5,
        ];
    }
}
