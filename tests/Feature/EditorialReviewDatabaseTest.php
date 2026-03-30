<?php

use App\Enums\EditorialSectionType;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\EditorialReview;
use App\Models\EditorialReviewChapterNote;
use App\Models\EditorialReviewSection;
use App\Models\Storyline;

test('EditorialSectionType enum has 8 cases', function () {
    expect(EditorialSectionType::cases())->toHaveCount(8);
});

test('EditorialSectionType enum values are snake_case strings', function () {
    expect(EditorialSectionType::Plot->value)->toBe('plot')
        ->and(EditorialSectionType::Characters->value)->toBe('characters')
        ->and(EditorialSectionType::Pacing->value)->toBe('pacing')
        ->and(EditorialSectionType::NarrativeVoice->value)->toBe('narrative_voice')
        ->and(EditorialSectionType::Themes->value)->toBe('themes')
        ->and(EditorialSectionType::SceneCraft->value)->toBe('scene_craft')
        ->and(EditorialSectionType::ProseStyle->value)->toBe('prose_style')
        ->and(EditorialSectionType::ChapterNotes->value)->toBe('chapter_notes');
});

test('EditorialReview factory creates a completed review', function () {
    $review = EditorialReview::factory()->create();

    expect($review->status)->toBe('completed')
        ->and($review->overall_score)->toBeInt()
        ->and($review->overall_score)->toBeGreaterThanOrEqual(40)
        ->and($review->executive_summary)->toBeString()
        ->and($review->top_strengths)->toBeArray()
        ->and($review->top_strengths)->toHaveCount(3)
        ->and($review->top_improvements)->toBeArray()
        ->and($review->top_improvements)->toHaveCount(3)
        ->and($review->started_at)->not->toBeNull()
        ->and($review->completed_at)->not->toBeNull();
});

test('EditorialReview factory pending state', function () {
    $review = EditorialReview::factory()->pending()->create();

    expect($review->status)->toBe('pending')
        ->and($review->overall_score)->toBeNull()
        ->and($review->executive_summary)->toBeNull()
        ->and($review->started_at)->toBeNull()
        ->and($review->completed_at)->toBeNull();
});

test('EditorialReview factory failed state', function () {
    $review = EditorialReview::factory()->failed()->create();

    expect($review->status)->toBe('failed')
        ->and($review->error_message)->toBeString()
        ->and($review->started_at)->not->toBeNull()
        ->and($review->completed_at)->toBeNull();
});

test('EditorialReview factory inProgress state', function () {
    $review = EditorialReview::factory()->inProgress()->create();

    expect($review->status)->toBe('analyzing')
        ->and($review->progress)->toBeArray()
        ->and($review->progress['phase'])->toBe('analyzing')
        ->and($review->progress['current_chapter'])->toBe(3)
        ->and($review->progress['total_chapters'])->toBe(12);
});

test('EditorialReview belongs to Book', function () {
    $book = Book::factory()->create();
    $review = EditorialReview::factory()->create(['book_id' => $book->id]);

    expect($review->book->id)->toBe($book->id);
});

test('EditorialReview has many sections', function () {
    $review = EditorialReview::factory()->create();
    EditorialReviewSection::factory()->count(3)->create(['editorial_review_id' => $review->id]);

    expect($review->sections)->toHaveCount(3);
});

test('EditorialReview has many chapter notes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $review = EditorialReview::factory()->create(['book_id' => $book->id]);

    EditorialReviewChapterNote::create([
        'editorial_review_id' => $review->id,
        'chapter_id' => $chapter->id,
        'notes' => ['narrative_voice' => ['pov' => 'third-person']],
    ]);

    expect($review->chapterNotes)->toHaveCount(1);
});

test('EditorialReview casts progress as array', function () {
    $progress = ['phase' => 'synthesizing', 'current_section' => 'Characters'];
    $review = EditorialReview::factory()->create(['progress' => $progress]);
    $review->refresh();

    expect($review->progress)->toBeArray()
        ->and($review->progress['phase'])->toBe('synthesizing')
        ->and($review->progress['current_section'])->toBe('Characters');
});

test('EditorialReview casts timestamps as datetime', function () {
    $review = EditorialReview::factory()->create();

    expect($review->started_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($review->completed_at)->toBeInstanceOf(DateTimeInterface::class);
});

test('EditorialReviewSection factory creates a section with findings', function () {
    $section = EditorialReviewSection::factory()->create();

    expect($section->type)->toBe(EditorialSectionType::Plot)
        ->and($section->score)->toBeInt()
        ->and($section->summary)->toBeString()
        ->and($section->findings)->toBeArray()
        ->and($section->findings[0])->toHaveKeys(['severity', 'description', 'chapter_references', 'recommendation'])
        ->and($section->recommendations)->toBeArray();
});

test('EditorialReviewSection casts type as EditorialSectionType enum', function () {
    $section = EditorialReviewSection::factory()->create(['type' => EditorialSectionType::NarrativeVoice]);

    expect($section->type)->toBe(EditorialSectionType::NarrativeVoice);
});

test('EditorialReviewSection belongs to EditorialReview', function () {
    $review = EditorialReview::factory()->create();
    $section = EditorialReviewSection::factory()->create(['editorial_review_id' => $review->id]);

    expect($section->editorialReview->id)->toBe($review->id);
});

test('EditorialReviewChapterNote belongs to EditorialReview and Chapter', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $review = EditorialReview::factory()->create(['book_id' => $book->id]);

    $note = EditorialReviewChapterNote::create([
        'editorial_review_id' => $review->id,
        'chapter_id' => $chapter->id,
        'notes' => [
            'narrative_voice' => ['pov' => 'first-person', 'tense' => 'past'],
            'themes' => ['motifs' => ['isolation']],
        ],
    ]);

    expect($note->editorialReview->id)->toBe($review->id)
        ->and($note->chapter->id)->toBe($chapter->id)
        ->and($note->notes)->toBeArray()
        ->and($note->notes['narrative_voice']['pov'])->toBe('first-person');
});

test('Book has many editorial reviews', function () {
    $book = Book::factory()->create();
    EditorialReview::factory()->count(2)->create(['book_id' => $book->id]);

    expect($book->editorialReviews)->toHaveCount(2);
});

test('Chapter has many editorial review chapter notes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $review = EditorialReview::factory()->create(['book_id' => $book->id]);

    EditorialReviewChapterNote::create([
        'editorial_review_id' => $review->id,
        'chapter_id' => $chapter->id,
        'notes' => ['themes' => ['motifs' => ['decay']]],
    ]);

    expect($chapter->editorialReviewChapterNotes)->toHaveCount(1);
});

test('deleting EditorialReview cascades to sections and chapter notes', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->create(['book_id' => $book->id]);
    $chapter = Chapter::factory()->create(['book_id' => $book->id, 'storyline_id' => $storyline->id]);
    $review = EditorialReview::factory()->create(['book_id' => $book->id]);

    EditorialReviewSection::factory()->count(3)->create(['editorial_review_id' => $review->id]);
    EditorialReviewChapterNote::create([
        'editorial_review_id' => $review->id,
        'chapter_id' => $chapter->id,
        'notes' => ['themes' => []],
    ]);

    expect(EditorialReviewSection::where('editorial_review_id', $review->id)->count())->toBe(3)
        ->and(EditorialReviewChapterNote::where('editorial_review_id', $review->id)->count())->toBe(1);

    $review->delete();

    expect(EditorialReviewSection::where('editorial_review_id', $review->id)->count())->toBe(0)
        ->and(EditorialReviewChapterNote::where('editorial_review_id', $review->id)->count())->toBe(0);
});
