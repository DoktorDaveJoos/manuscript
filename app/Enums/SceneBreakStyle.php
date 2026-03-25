<?php

namespace App\Enums;

enum SceneBreakStyle: string
{
    case Asterisks = 'asterisks';
    case Fleuron = 'fleuron';
    case Flourish = 'flourish';
    case Rule = 'rule';
    case Dots = 'dots';
    case Dashes = 'dashes';
    case BlankSpace = 'blank-space';
    case Ornament = 'ornament';

    public function label(): string
    {
        return match ($this) {
            self::Asterisks => '* * *',
            self::Fleuron => '❧',
            self::Flourish => '~❋~',
            self::Rule => '———',
            self::Dots => '• • •',
            self::Dashes => '— — —',
            self::BlankSpace => '(blank space)',
            self::Ornament => '✦',
        };
    }

    public function html(): string
    {
        return match ($this) {
            self::Asterisks => '<p class="scene-break scene-break--asterisks">*&nbsp;&nbsp;*&nbsp;&nbsp;*</p>',
            self::Fleuron => '<p class="scene-break scene-break--fleuron">❧</p>',
            self::Flourish => '<p class="scene-break scene-break--flourish">~❋~</p>',
            self::Rule => '<hr class="scene-break scene-break--rule" />',
            self::Dots => '<p class="scene-break scene-break--dots">•&nbsp;&nbsp;•&nbsp;&nbsp;•</p>',
            self::Dashes => '<p class="scene-break scene-break--dashes">—&nbsp;&nbsp;—&nbsp;&nbsp;—</p>',
            self::BlankSpace => '<div class="scene-break scene-break--blank">&nbsp;</div>',
            self::Ornament => '<p class="scene-break scene-break--ornament">✦</p>',
        };
    }

    public function xhtml(): string
    {
        return match ($this) {
            self::Rule => '<hr class="scene-break scene-break--rule" />',
            self::BlankSpace => '<div class="scene-break scene-break--blank">&#160;</div>',
            default => str_replace('&nbsp;', '&#160;', $this->html()),
        };
    }

    public function plainText(): string
    {
        return match ($this) {
            self::Asterisks => '* * *',
            self::Fleuron => '❧',
            self::Flourish => '~❋~',
            self::Rule => '---',
            self::Dots => '• • •',
            self::Dashes => '— — —',
            self::BlankSpace => '',
            self::Ornament => '✦',
        };
    }
}
