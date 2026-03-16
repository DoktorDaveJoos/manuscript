<?php

namespace App\Enums;

enum Genre: string
{
    case Thriller = 'thriller';
    case Mystery = 'mystery';
    case Romance = 'romance';
    case ScienceFiction = 'science_fiction';
    case Fantasy = 'fantasy';
    case Horror = 'horror';
    case LiteraryFiction = 'literary_fiction';
    case HistoricalFiction = 'historical_fiction';
    case Crime = 'crime';
    case Adventure = 'adventure';
    case Drama = 'drama';
    case YoungAdult = 'young_adult';
    case NonFiction = 'non_fiction';
    case Memoir = 'memoir';
    case Poetry = 'poetry';
    case Western = 'western';
    case Dystopian = 'dystopian';

    public function label(): string
    {
        return match ($this) {
            self::Thriller => 'Thriller',
            self::Mystery => 'Mystery',
            self::Romance => 'Romance',
            self::ScienceFiction => 'Science Fiction',
            self::Fantasy => 'Fantasy',
            self::Horror => 'Horror',
            self::LiteraryFiction => 'Literary Fiction',
            self::HistoricalFiction => 'Historical Fiction',
            self::Crime => 'Crime',
            self::Adventure => 'Adventure',
            self::Drama => 'Drama',
            self::YoungAdult => 'Young Adult',
            self::NonFiction => 'Non-Fiction',
            self::Memoir => 'Memoir',
            self::Poetry => 'Poetry',
            self::Western => 'Western',
            self::Dystopian => 'Dystopian',
        };
    }
}
