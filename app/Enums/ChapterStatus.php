<?php

namespace App\Enums;

enum ChapterStatus: string
{
    case Draft = 'draft';
    case Revised = 'revised';
    case Final = 'final';
}
