<?php

namespace App\Enums;

enum VersionSource: string
{
    case Original = 'original';
    case AiRevision = 'ai_revision';
    case ManualEdit = 'manual_edit';
    case Normalization = 'normalization';
    case Beautify = 'beautify';
    case Snapshot = 'snapshot';
    case ContinueWriting = 'continue_writing';
    case RewriteSelection = 'rewrite_selection';
    case EditorialRewrite = 'editorial_rewrite';
}
