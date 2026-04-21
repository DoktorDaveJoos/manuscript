<?php

namespace App\Enums;

enum PlotCoachStage: string
{
    case Intake = 'intake';
    case Structure = 'structure';
    case Plotting = 'plotting';
    case Entities = 'entities';
    case Refinement = 'refinement';
    case Complete = 'complete';
}
