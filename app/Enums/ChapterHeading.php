<?php

namespace App\Enums;

enum ChapterHeading: string
{
    case None = 'none';
    case Number = 'number';
    case Full = 'full';

    /**
     * Whether the chapter-number label (e.g. "Chapter 1") is shown.
     */
    public function showsNumber(): bool
    {
        return $this !== self::None;
    }

    /**
     * Whether the chapter title is shown alongside the number.
     */
    public function showsTitle(): bool
    {
        return $this === self::Full;
    }
}
