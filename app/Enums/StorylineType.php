<?php

namespace App\Enums;

enum StorylineType: string
{
    case Main = 'main';
    case Backstory = 'backstory';
    case Parallel = 'parallel';
}
