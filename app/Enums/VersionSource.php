<?php

namespace App\Enums;

enum VersionSource: string
{
    case Original = 'original';
    case AiRevision = 'ai_revision';
    case ManualEdit = 'manual_edit';
}
