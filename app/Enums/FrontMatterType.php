<?php

namespace App\Enums;

enum FrontMatterType: string
{
    case TitlePage = 'title-page';
    case Copyright = 'copyright';
    case Toc = 'toc';
}
