<?php

namespace App\Enums;

enum PlotPointType: string
{
    case Setup = 'setup';
    case Conflict = 'conflict';
    case TurningPoint = 'turning_point';
    case Resolution = 'resolution';
    case Worldbuilding = 'worldbuilding';
}
