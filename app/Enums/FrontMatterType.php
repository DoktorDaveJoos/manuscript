<?php

namespace App\Enums;

enum FrontMatterType: string
{
    case TitlePage = 'title-page';
    case Copyright = 'copyright';
    case Dedication = 'dedication';
    case Epigraph = 'epigraph';
    case Toc = 'toc';
}
