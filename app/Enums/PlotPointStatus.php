<?php

namespace App\Enums;

enum PlotPointStatus: string
{
    case Planned = 'planned';
    case Fulfilled = 'fulfilled';
    case Abandoned = 'abandoned';
}
