<?php

namespace App\Enums;

enum TrimSize: string
{
    case Pocket = '5x8';
    case Digest = '5.25x8';
    case SmallTrade = '5.5x8.5';
    case UsTrade = '6x9';
    case Royal = '7x10';
    case Manuscript = '8.5x11';

    public function label(): string
    {
        return match ($this) {
            self::Pocket => '5" × 8"',
            self::Digest => '5.25" × 8"',
            self::SmallTrade => '5.5" × 8.5"',
            self::UsTrade => '6" × 9"',
            self::Royal => '7" × 10"',
            self::Manuscript => '8.5" × 11"',
        };
    }

    /**
     * @return array{width: float, height: float}
     */
    public function dimensions(): array
    {
        return match ($this) {
            self::Pocket => ['width' => 127, 'height' => 203],
            self::Digest => ['width' => 133, 'height' => 203],
            self::SmallTrade => ['width' => 140, 'height' => 216],
            self::UsTrade => ['width' => 152, 'height' => 229],
            self::Royal => ['width' => 178, 'height' => 254],
            self::Manuscript => ['width' => 216, 'height' => 279],
        };
    }

    /**
     * @return array{top: int, bottom: int, outer: int, gutter: int}
     */
    public function margins(): array
    {
        return match ($this) {
            self::Pocket => ['top' => 16, 'bottom' => 16, 'outer' => 14, 'gutter' => 19],
            self::Digest => ['top' => 16, 'bottom' => 16, 'outer' => 14, 'gutter' => 20],
            self::SmallTrade => ['top' => 19, 'bottom' => 19, 'outer' => 16, 'gutter' => 22],
            self::UsTrade => ['top' => 19, 'bottom' => 19, 'outer' => 16, 'gutter' => 22],
            self::Royal => ['top' => 22, 'bottom' => 22, 'outer' => 19, 'gutter' => 25],
            self::Manuscript => ['top' => 25, 'bottom' => 25, 'outer' => 25, 'gutter' => 25],
        };
    }
}
