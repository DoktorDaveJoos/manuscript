<?php

namespace App\Enums;

enum CharacterPlotPointRole: string
{
    case Key = 'key';
    case Supporting = 'supporting';
    case Mentioned = 'mentioned';
}
