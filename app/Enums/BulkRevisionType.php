<?php

namespace App\Enums;

use App\Ai\Agents\ProseReviser;
use App\Ai\Agents\TextBeautifier;
use App\Models\Book;
use App\Models\Chapter;
use Laravel\Ai\Contracts\Agent;

enum BulkRevisionType: string
{
    case Beautify = 'beautify';
    case Revise = 'revise';

    public function agent(Book $book, Chapter $chapter): Agent
    {
        return match ($this) {
            self::Beautify => new TextBeautifier($book),
            self::Revise => new ProseReviser($book, $chapter),
        };
    }

    public function promptPrefix(): string
    {
        return match ($this) {
            self::Beautify => __('Restructure the following chapter text:'),
            self::Revise => __('Revise the following chapter text:'),
        };
    }

    public function versionSource(): VersionSource
    {
        return match ($this) {
            self::Beautify => VersionSource::Beautify,
            self::Revise => VersionSource::AiRevision,
        };
    }

    public function changeSummary(): string
    {
        return match ($this) {
            self::Beautify => __('AI text beautification'),
            self::Revise => __('AI prose revision'),
        };
    }
}
