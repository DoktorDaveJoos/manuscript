<?php

namespace App\Enums;

enum EditorialSectionType: string
{
    case Plot = 'plot';
    case Characters = 'characters';
    case Pacing = 'pacing';
    case NarrativeVoice = 'narrative_voice';
    case Themes = 'themes';
    case SceneCraft = 'scene_craft';
    case ProseStyle = 'prose_style';
    case ChapterNotes = 'chapter_notes';
}
