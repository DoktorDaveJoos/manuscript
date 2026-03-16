<?php

namespace App\Enums;

enum AiTaskCategory: string
{
    case Writing = 'writing';
    case Analysis = 'analysis';
    case Extraction = 'extraction';

    public function column(): string
    {
        return match ($this) {
            self::Writing => 'writing_model',
            self::Analysis => 'analysis_model',
            self::Extraction => 'extraction_model',
        };
    }
}
