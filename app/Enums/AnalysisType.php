<?php

namespace App\Enums;

enum AnalysisType: string
{
    case Pacing = 'pacing';
    case Plothole = 'plothole';
    case CharacterConsistency = 'character_consistency';
    case Density = 'density';
    case PlotDeviation = 'plot_deviation';
    case NextChapterSuggestion = 'next_chapter_suggestion';
}
