<?php

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\EditorialNotesAgent;
use App\Ai\Agents\EditorialSummaryAgent;
use App\Ai\Agents\EditorialSynthesisAgent;
use App\Enums\EditorialSectionType;
use App\Jobs\RunEditorialReviewJob;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\EditorialReview;
use App\Models\EditorialReviewSection;
use App\Models\License;
use App\Models\Scene;
use App\Models\Storyline;

beforeEach(function () {
    License::factory()->create();
});

function fakeAllEditorialAgents(): void
{
    EditorialNotesAgent::fake(function () {
        return [
            'narrative_voice' => [
                'pov' => 'third-person limited',
                'tense' => 'past',
                'observations' => ['Consistent POV throughout'],
                'tone_notes' => 'Dark and brooding',
            ],
            'themes' => [
                'motifs' => ['isolation', 'decay'],
                'observations' => ['Mirror motif recurs'],
            ],
            'scene_craft' => [
                'scene_purposes' => ['setup'],
                'show_vs_tell' => ['Paragraph 5 tells grief rather than showing'],
                'sensory_detail' => 'Heavy on visual, lacks auditory',
            ],
            'prose_style_patterns' => [
                'sentence_rhythm' => 'Varied in dialogue, monotonous in action',
                'repetitions' => ['"suddenly" appears 3 times'],
                'vocabulary_notes' => 'Vocabulary narrows in emotional scenes',
            ],
        ];
    });

    EditorialSynthesisAgent::fake(function () {
        return [
            'score' => 72,
            'summary' => 'The section shows strong fundamentals with room for improvement.',
            'findings' => [
                [
                    'severity' => 'warning',
                    'description' => 'Minor inconsistency detected.',
                    'chapter_references' => [1],
                    'recommendation' => 'Review the passage for consistency.',
                ],
            ],
            'recommendations' => ['Tighten prose in action scenes.'],
        ];
    });

    EditorialSummaryAgent::fake(function () {
        return [
            'overall_score' => 75,
            'executive_summary' => 'This is a solid manuscript with clear strengths in character development.',
            'top_strengths' => ['Strong characters', 'Compelling plot', 'Vivid settings'],
            'top_improvements' => ['Pacing in middle act', 'Dialogue tags', 'Tighter prose'],
            'is_pre_editorial' => false,
        ];
    });
}

function createBookWithChaptersForEditorial(int $chapterCount = 3): array
{
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapters = [];

    for ($i = 1; $i <= $chapterCount; $i++) {
        $content = "<p>Chapter {$i} content. ".fake()->paragraphs(3, true).'</p>';
        $chapter = Chapter::factory()->for($book)->for($storyline)->create([
            'reader_order' => $i,
            'title' => "Chapter {$i}",
            'content_hash' => hash('xxh128', strip_tags($content)),
            'prepared_content_hash' => hash('xxh128', strip_tags($content)),
        ]);
        ChapterVersion::factory()->for($chapter)->create([
            'is_current' => true,
            'content' => $content,
        ]);
        Scene::factory()->for($chapter)->create([
            'content' => $content,
            'sort_order' => 0,
            'word_count' => str_word_count(strip_tags($content)),
        ]);
        $chapters[] = $chapter;
    }

    return [$book, $chapters];
}

test('RunEditorialReviewJob completes all four phases successfully', function () {
    ChapterAnalyzer::fake(function () {
        return [
            'summary' => 'Chapter summary.',
            'key_events' => ['Event 1'],
            'characters_present' => ['John'],
            'tension_score' => 7,
            'micro_tension_score' => 6,
            'scene_purpose' => 'turning_point',
            'value_shift' => 'safety → danger',
            'emotional_state_open' => 'cautious',
            'emotional_state_close' => 'terrified',
            'emotional_shift_magnitude' => 8,
            'hook_score' => 8,
            'hook_type' => 'cliffhanger',
            'hook_reasoning' => 'Strong ending.',
            'entry_hook_score' => 7,
            'pacing_feel' => 'brisk',
            'sensory_grounding' => 4,
            'information_delivery' => 'organic',
        ];
    });
    fakeAllEditorialAgents();

    [$book, $chapters] = createBookWithChaptersForEditorial(2);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'pending']);

    $job = new RunEditorialReviewJob($book, $review);
    $job->handle();

    $review->refresh();

    expect($review->status)->toBe('completed')
        ->and($review->completed_at)->not->toBeNull()
        ->and($review->overall_score)->toBe(75)
        ->and($review->executive_summary)->toContain('solid manuscript')
        ->and($review->top_strengths)->toHaveCount(3)
        ->and($review->top_improvements)->toHaveCount(3);

    expect($review->sections()->count())->toBe(8);
    expect($review->chapterNotes()->count())->toBe(2);

    foreach (EditorialSectionType::cases() as $sectionType) {
        $section = $review->sections()->where('type', $sectionType)->first();
        expect($section)->not->toBeNull()
            ->and($section->score)->toBe(72)
            ->and($section->summary)->not->toBeEmpty()
            ->and($section->findings)->toBeArray()
            ->and($section->recommendations)->toBeArray();
    }
});

test('RunEditorialReviewJob refreshes stale chapter analyses in Phase 0', function () {
    ChapterAnalyzer::fake(function () {
        return [
            'summary' => 'Refreshed summary.',
            'key_events' => ['Event 1'],
            'characters_present' => ['John'],
            'tension_score' => 5,
            'micro_tension_score' => 4,
            'scene_purpose' => 'setup',
            'value_shift' => null,
            'emotional_state_open' => 'calm',
            'emotional_state_close' => 'worried',
            'emotional_shift_magnitude' => 3,
            'hook_score' => 6,
            'hook_type' => 'soft_hook',
            'hook_reasoning' => 'Moderate tension.',
            'entry_hook_score' => 5,
            'pacing_feel' => 'measured',
            'sensory_grounding' => 3,
            'information_delivery' => 'mixed',
        ];
    });
    fakeAllEditorialAgents();

    [$book, $chapters] = createBookWithChaptersForEditorial(1);

    $chapter = $chapters[0];
    $chapter->update([
        'prepared_content_hash' => 'stale_hash',
    ]);

    $review = EditorialReview::factory()->for($book)->create(['status' => 'pending']);

    $job = new RunEditorialReviewJob($book, $review);
    $job->handle();

    $chapter->refresh();
    $review->refresh();

    expect($chapter->summary)->toBe('Refreshed summary.')
        ->and($chapter->tension_score)->toBe(5)
        ->and($chapter->prepared_content_hash)->toBe($chapter->content_hash)
        ->and($review->status)->toBe('completed');

    ChapterAnalyzer::assertPrompted(fn ($prompt) => true);
});

test('RunEditorialReviewJob marks review as failed when no AI provider configured', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $review = EditorialReview::factory()->for($book)->create(['status' => 'pending']);

    $job = new RunEditorialReviewJob($book, $review);
    $job->handle();

    $review->refresh();

    expect($review->status)->toBe('failed')
        ->and($review->error_message)->toBe('No AI provider configured.');
});

test('RunEditorialReviewJob handles individual chapter failures gracefully', function () {
    $callCount = 0;
    EditorialNotesAgent::fake(function () use (&$callCount) {
        $callCount++;
        if ($callCount === 1) {
            throw new RuntimeException('API error on chapter 1');
        }

        return [
            'narrative_voice' => ['pov' => 'first-person', 'tense' => 'present', 'observations' => [], 'tone_notes' => 'Light'],
            'themes' => ['motifs' => [], 'observations' => []],
            'scene_craft' => ['scene_purposes' => [], 'show_vs_tell' => [], 'sensory_detail' => 'Balanced'],
            'prose_style_patterns' => ['sentence_rhythm' => 'Varied', 'repetitions' => [], 'vocabulary_notes' => 'Rich'],
        ];
    });
    ChapterAnalyzer::fake(function () {
        return [
            'summary' => 'Summary.', 'key_events' => [], 'characters_present' => [],
            'tension_score' => 5, 'micro_tension_score' => 4, 'scene_purpose' => 'setup',
            'value_shift' => null, 'emotional_state_open' => 'calm', 'emotional_state_close' => 'calm',
            'emotional_shift_magnitude' => 1, 'hook_score' => 5, 'hook_type' => 'soft_hook',
            'hook_reasoning' => 'Ok.', 'entry_hook_score' => 5, 'pacing_feel' => 'measured',
            'sensory_grounding' => 3, 'information_delivery' => 'mixed',
        ];
    });
    EditorialSynthesisAgent::fake(function () {
        return [
            'score' => 65, 'summary' => 'Section summary.',
            'findings' => [], 'recommendations' => [],
        ];
    });
    EditorialSummaryAgent::fake(function () {
        return [
            'overall_score' => 68, 'executive_summary' => 'Good work overall.',
            'top_strengths' => ['A', 'B', 'C'], 'top_improvements' => ['D', 'E', 'F'],
            'is_pre_editorial' => false,
        ];
    });

    [$book, $chapters] = createBookWithChaptersForEditorial(2);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'pending']);

    $job = new RunEditorialReviewJob($book, $review);
    $job->handle();

    $review->refresh();

    expect($review->status)->toBe('completed')
        ->and($review->chapterNotes()->count())->toBe(1);
});

test('RunEditorialReviewJob marks review as failed on catastrophic error', function () {
    EditorialNotesAgent::fake(function () {
        return [
            'narrative_voice' => ['pov' => 'first', 'tense' => 'past', 'observations' => [], 'tone_notes' => ''],
            'themes' => ['motifs' => [], 'observations' => []],
            'scene_craft' => ['scene_purposes' => [], 'show_vs_tell' => [], 'sensory_detail' => ''],
            'prose_style_patterns' => ['sentence_rhythm' => '', 'repetitions' => [], 'vocabulary_notes' => ''],
        ];
    });
    ChapterAnalyzer::fake(function () {
        return [
            'summary' => 'Summary.', 'key_events' => [], 'characters_present' => [],
            'tension_score' => 5, 'micro_tension_score' => 4, 'scene_purpose' => 'setup',
            'value_shift' => null, 'emotional_state_open' => 'calm', 'emotional_state_close' => 'calm',
            'emotional_shift_magnitude' => 1, 'hook_score' => 5, 'hook_type' => 'soft_hook',
            'hook_reasoning' => 'Ok.', 'entry_hook_score' => 5, 'pacing_feel' => 'measured',
            'sensory_grounding' => 3, 'information_delivery' => 'mixed',
        ];
    });
    EditorialSynthesisAgent::fake(function () {
        throw new RuntimeException('Fatal synthesis error');
    });

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'pending']);

    $job = new RunEditorialReviewJob($book, $review);
    $job->handle();

    $review->refresh();

    expect($review->status)->toBe('failed')
        ->and($review->error_message)->toContain('Fatal synthesis error');
});

test('RunEditorialReviewJob updates progress throughout pipeline', function () {
    ChapterAnalyzer::fake(function () {
        return [
            'summary' => 'Summary.', 'key_events' => [], 'characters_present' => [],
            'tension_score' => 5, 'micro_tension_score' => 4, 'scene_purpose' => 'setup',
            'value_shift' => null, 'emotional_state_open' => 'calm', 'emotional_state_close' => 'calm',
            'emotional_shift_magnitude' => 1, 'hook_score' => 5, 'hook_type' => 'soft_hook',
            'hook_reasoning' => 'Ok.', 'entry_hook_score' => 5, 'pacing_feel' => 'measured',
            'sensory_grounding' => 3, 'information_delivery' => 'mixed',
        ];
    });
    fakeAllEditorialAgents();

    [$book, $chapters] = createBookWithChaptersForEditorial(1);
    $review = EditorialReview::factory()->for($book)->create(['status' => 'pending']);

    $job = new RunEditorialReviewJob($book, $review);
    $job->handle();

    $review->refresh();

    expect($review->status)->toBe('completed')
        ->and($review->progress)->toBeArray()
        ->and($review->progress['phase'])->toBeIn(['synthesizing', 'analyzing', 'refreshing']);
});

test('EditorialSectionType enum has all eight cases', function () {
    $cases = EditorialSectionType::cases();

    expect($cases)->toHaveCount(8)
        ->and(collect($cases)->pluck('value')->all())->toBe([
            'plot', 'characters', 'pacing', 'narrative_voice',
            'themes', 'scene_craft', 'prose_style', 'chapter_notes',
        ]);
});

test('EditorialReview model has correct relationships', function () {
    [$book] = createBookWithChaptersForEditorial(1);
    $review = EditorialReview::factory()->for($book)->create();

    expect($review->book)->toBeInstanceOf(Book::class)
        ->and($review->sections)->toBeEmpty()
        ->and($review->chapterNotes)->toBeEmpty();
});

test('RunEditorialReviewJob fails gracefully when all chapters have empty content', function () {
    ChapterAnalyzer::fake(function () {
        return [
            'summary' => 'Summary.', 'key_events' => [], 'characters_present' => [],
            'tension_score' => 5, 'micro_tension_score' => 4, 'scene_purpose' => 'setup',
            'value_shift' => null, 'emotional_state_open' => 'calm', 'emotional_state_close' => 'calm',
            'emotional_shift_magnitude' => 1, 'hook_score' => 5, 'hook_type' => 'soft_hook',
            'hook_reasoning' => 'Ok.', 'entry_hook_score' => 5, 'pacing_feel' => 'measured',
            'sensory_grounding' => 3, 'information_delivery' => 'mixed',
        ];
    });
    fakeAllEditorialAgents();

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 1,
        'title' => 'Empty Chapter',
        'content_hash' => null,
        'prepared_content_hash' => null,
    ]);
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => '',
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '',
        'sort_order' => 0,
        'word_count' => 0,
    ]);

    $review = EditorialReview::factory()->for($book)->create(['status' => 'pending']);

    $job = new RunEditorialReviewJob($book, $review);
    $job->handle();

    $review->refresh();

    expect($review->status)->toBe('failed')
        ->and($review->error_message)->toContain('No chapter content');
});

test('findingKey produces xxh128 length hash', function () {
    $key = EditorialReviewSection::findingKey('plot', 'Some finding description');

    expect(strlen($key))->toBe(32);
});
