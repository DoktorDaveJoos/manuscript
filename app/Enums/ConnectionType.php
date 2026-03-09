<?php

namespace App\Enums;

enum ConnectionType: string
{
    case Causes = 'causes';
    case SetsUp = 'sets_up';
    case Resolves = 'resolves';
    case Contradicts = 'contradicts';
}
