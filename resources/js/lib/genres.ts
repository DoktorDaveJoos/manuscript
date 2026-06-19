/**
 * Book genres, grouped by category for <optgroup> rendering.
 *
 * Mirrors the backend `App\Enums\Genre` enum — values MUST match the enum's
 * string values exactly, since that enum is the validation source of truth.
 * Used by both the create-book dialog and the general settings page.
 */

export type GenreOption = { value: string; label: string };

export type GenreGroup = { label: string; options: GenreOption[] };

export const GENRE_GROUPS: GenreGroup[] = [
    {
        label: 'Fiction',
        options: [
            { value: 'thriller', label: 'Thriller' },
            { value: 'mystery', label: 'Mystery' },
            { value: 'romance', label: 'Romance' },
            { value: 'science_fiction', label: 'Science Fiction' },
            { value: 'fantasy', label: 'Fantasy' },
            { value: 'horror', label: 'Horror' },
            { value: 'literary_fiction', label: 'Literary Fiction' },
            { value: 'historical_fiction', label: 'Historical Fiction' },
            { value: 'crime', label: 'Crime' },
            { value: 'adventure', label: 'Adventure' },
            { value: 'drama', label: 'Drama' },
            { value: 'western', label: 'Western' },
            { value: 'dystopian', label: 'Dystopian' },
        ],
    },
    {
        label: 'Children & Young Readers',
        options: [
            { value: 'picture_book', label: 'Picture Book' },
            { value: 'early_reader', label: 'Early Reader' },
            { value: 'chapter_book', label: 'Chapter Book' },
            { value: 'middle_grade', label: 'Middle Grade' },
            { value: 'young_adult', label: 'Young Adult' },
        ],
    },
    {
        label: 'Non-Fiction',
        options: [
            { value: 'non_fiction', label: 'Non-Fiction' },
            { value: 'memoir', label: 'Memoir' },
            { value: 'biography', label: 'Biography' },
            { value: 'self_help', label: 'Self-Help' },
            { value: 'history', label: 'History' },
            { value: 'popular_science', label: 'Popular Science' },
            { value: 'travel', label: 'Travel' },
            { value: 'true_crime', label: 'True Crime' },
            { value: 'essay', label: 'Essay' },
        ],
    },
    {
        label: 'Guides & Reference',
        options: [
            { value: 'how_to_guide', label: 'How-To Guide' },
            { value: 'reference', label: 'Reference' },
            { value: 'cookbook', label: 'Cookbook' },
            { value: 'handbook', label: 'Handbook' },
        ],
    },
    {
        label: 'Academic',
        options: [
            { value: 'academic', label: 'Academic / Scholarly' },
            { value: 'textbook', label: 'Textbook' },
            { value: 'dissertation', label: 'Thesis / Dissertation' },
            { value: 'research_paper', label: 'Research Paper' },
        ],
    },
    {
        label: 'Poetry & Other',
        options: [{ value: 'poetry', label: 'Poetry' }],
    },
];

const GENRE_LABELS: Record<string, string> = Object.fromEntries(
    GENRE_GROUPS.flatMap((group) => group.options).map(({ value, label }) => [
        value,
        label,
    ]),
);

/** Resolve a genre value to its display label, falling back to the raw value. */
export function genreLabel(value: string): string {
    return GENRE_LABELS[value] ?? value;
}
