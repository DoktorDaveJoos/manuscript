<?php

namespace App\Services;

use App\Models\Book;
use App\Models\License;

class FreeTierLimits
{
    public const MAX_BOOKS = 1;

    public const MAX_STORYLINES = 1;

    public const MAX_WIKI_ENTRIES = 5;

    /** @var string[] */
    public const FREE_EXPORT_FORMATS = ['docx', 'txt'];

    public static function canCreateBook(): bool
    {
        return License::isActive() || Book::count() < self::MAX_BOOKS;
    }

    public static function bookCount(): int
    {
        return Book::count();
    }

    public static function canCreateStoryline(Book $book): bool
    {
        return License::isActive() || $book->storylines()->count() < self::MAX_STORYLINES;
    }

    public static function storylineCount(Book $book): int
    {
        return $book->storylines()->count();
    }

    public static function canCreateWikiEntry(Book $book): bool
    {
        return License::isActive() || self::wikiEntryCount($book) < self::MAX_WIKI_ENTRIES;
    }

    public static function wikiEntryCount(Book $book): int
    {
        return $book->characters()->count() + $book->wikiEntries()->count();
    }

    public static function canExportFormat(string $format): bool
    {
        return License::isActive() || in_array($format, self::FREE_EXPORT_FORMATS, true);
    }

    public static function isProExportFormat(string $format): bool
    {
        return ! in_array($format, self::FREE_EXPORT_FORMATS, true);
    }
}
