<?php

namespace App\Enums;

enum AnalysisType: string
{
    case Plothole = 'plothole';
    case CharacterConsistency = 'character_consistency';
    case PlotDeviation = 'plot_deviation';
    case NextChapterSuggestion = 'next_chapter_suggestion';
    case GenreHealth = 'thriller_health';
}
