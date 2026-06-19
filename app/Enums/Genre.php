<?php

namespace App\Enums;

enum Genre: string
{
    // Fiction
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
    case Western = 'western';
    case Dystopian = 'dystopian';

    // Children & Young Readers
    case PictureBook = 'picture_book';
    case EarlyReader = 'early_reader';
    case ChapterBook = 'chapter_book';
    case MiddleGrade = 'middle_grade';
    case YoungAdult = 'young_adult';

    // Non-Fiction
    case NonFiction = 'non_fiction';
    case Memoir = 'memoir';
    case Biography = 'biography';
    case SelfHelp = 'self_help';
    case History = 'history';
    case PopularScience = 'popular_science';
    case Travel = 'travel';
    case TrueCrime = 'true_crime';
    case Essay = 'essay';

    // Guides & Reference
    case HowToGuide = 'how_to_guide';
    case Reference = 'reference';
    case Cookbook = 'cookbook';
    case Handbook = 'handbook';

    // Academic
    case Academic = 'academic';
    case Textbook = 'textbook';
    case Dissertation = 'dissertation';
    case ResearchPaper = 'research_paper';

    // Poetry & Other
    case Poetry = 'poetry';

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
            self::Western => 'Western',
            self::Dystopian => 'Dystopian',
            self::PictureBook => 'Picture Book',
            self::EarlyReader => 'Early Reader',
            self::ChapterBook => 'Chapter Book',
            self::MiddleGrade => 'Middle Grade',
            self::YoungAdult => 'Young Adult',
            self::NonFiction => 'Non-Fiction',
            self::Memoir => 'Memoir',
            self::Biography => 'Biography',
            self::SelfHelp => 'Self-Help',
            self::History => 'History',
            self::PopularScience => 'Popular Science',
            self::Travel => 'Travel',
            self::TrueCrime => 'True Crime',
            self::Essay => 'Essay',
            self::HowToGuide => 'How-To Guide',
            self::Reference => 'Reference',
            self::Cookbook => 'Cookbook',
            self::Handbook => 'Handbook',
            self::Academic => 'Academic / Scholarly',
            self::Textbook => 'Textbook',
            self::Dissertation => 'Thesis / Dissertation',
            self::ResearchPaper => 'Research Paper',
            self::Poetry => 'Poetry',
        };
    }
}
