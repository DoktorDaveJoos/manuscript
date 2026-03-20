<?php

namespace App\Enums;

enum BeatStatus: string
{
    case Planned = 'planned';
    case Fulfilled = 'fulfilled';
    case Abandoned = 'abandoned';
}
