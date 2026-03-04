<?php

namespace App\Enums;

enum CharacterRole: string
{
    case Protagonist = 'protagonist';
    case Supporting = 'supporting';
    case Mentioned = 'mentioned';
}
