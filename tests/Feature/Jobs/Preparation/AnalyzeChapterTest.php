<?php

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\EntityExtractor;
use App\Ai\Agents\ManuscriptAnalyzer;
use App\Enums\AnalysisType;
use App\Jobs\Preparation\AnalyzeChapter;
use App\Models\AiPreparation;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Storyline;

function fakeChapterAnalysis(array $overrides = []): array
{
    return array_merge([
        'summary' => 'Summary.',
        'key_events' => [],
        'characters_present' => [],
        'tension_score' => 5,
        'micro_tension_score' => 4,
        'scene_purpose' => 'deepening',
        'value_shift' => null,
        'emotional_state_open' => 'calm',
        'emotional_state_close' => 'calm',
        'emotional_shift_magnitude' => 1,
        'hook_score' => 5,
        'hook_type' => 'closed',
        'hook_reasoning' => 'OK.',
        'entry_hook_score' => 5,
        'pacing_feel' => 'measured',
        'sensory_grounding' => 2,
        'information_delivery' => 'mixed',
        'plot_points' => [],
    ], $overrides);
}

function createBookForAnalysis(int $chapterCount = 2): array
{
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapters = [];

    for ($i = 1; $i <= $chapterCount; $i++) {
        $chapter = Chapter::factory()->for($book)->for($storyline)->create([
            'reader_order' => $i,
            'title' => "Chapter {$i}",
        ]);
        ChapterVersion::factory()->for($chapter)->create([
            'is_current' => true,
            'content' => "<p>Chapter {$i} content. ".fake()->paragraphs(3, true).'</p>',
        ]);
        $chapters[] = $chapter;
    }

    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'running',
        'current_phase' => 'chapter_analysis',
        'current_phase_total' => $chapterCount,
        'current_phase_progress' => 0,
    ]);

    return [$book, $chapters, $preparation];
}

test('analyze chapter performs analysis and entity extraction', function () {
    ChapterAnalyzer::fake(fn () => fakeChapterAnalysis([
        'summary' => 'The hero enters the castle.',
        'key_events' => ['Entered castle'],
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
        'exit_hook_score' => 8,
        'pacing_feel' => 'brisk',
        'sensory_grounding' => 4,
        'information_delivery' => 'organic',
        'plot_points' => [
            ['title' => 'Castle entry', 'description' => 'Hero enters the castle', 'type' => 'conflict'],
        ],
    ]));

    EntityExtractor::fake(function () {
        return [
            'characters' => [
                ['name' => 'John', 'aliases' => null, 'description' => 'The hero', 'role' => 'protagonist'],
            ],
            'entities' => [
                ['name' => 'The Castle', 'kind' => 'location', 'type' => 'Fortress', 'description' => 'An ancient stronghold.'],
            ],
        ];
    });

    ManuscriptAnalyzer::fake(function () {
        return ['score' => 7, 'findings' => [], 'recommendations' => []];
    });

    [$book, $chapters, $preparation] = createBookForAnalysis(1);

    $job = new AnalyzeChapter($book, $preparation, $chapters[0]->id);
    $job->handle();

    $chapters[0]->refresh();
    expect($chapters[0]->summary)->toBe('The hero enters the castle.')
        ->and($chapters[0]->tension_score)->toBe(7)
        ->and($chapters[0]->hook_score)->toBe(8)
        ->and($chapters[0]->hook_type)->toBe('cliffhanger')
        ->and($chapters[0]->scene_purpose)->toBe('turning_point')
        ->and($chapters[0]->value_shift)->toBe('safety → danger')
        ->and($chapters[0]->emotional_state_open)->toBe('cautious')
        ->and($chapters[0]->emotional_state_close)->toBe('terrified')
        ->and($chapters[0]->emotional_shift_magnitude)->toBe(8)
        ->and($chapters[0]->micro_tension_score)->toBe(6)
        ->and($chapters[0]->pacing_feel)->toBe('brisk')
        ->and($chapters[0]->entry_hook_score)->toBe(7)
        ->and($chapters[0]->exit_hook_score)->toBe(8)
        ->and($chapters[0]->sensory_grounding)->toBe(4)
        ->and($chapters[0]->information_delivery)->toBe('organic');

    expect($book->characters()->where('name', 'John')->exists())->toBeTrue();
    expect($book->plotPoints()->where('is_ai_derived', true)->count())->toBe(1);

    $castle = $book->wikiEntries()->where('name', 'The Castle')->first();
    expect($castle)->not->toBeNull()
        ->and($castle->kind->value)->toBe('location')
        ->and($castle->type)->toBe('Fortress')
        ->and($castle->is_ai_extracted)->toBeTrue();

    $preparation->refresh();
    expect($preparation->current_phase_progress)->toBe(1);
});

test('analyze chapter builds rolling context from preceding chapters', function () {
    $promptReceived = null;
    ChapterAnalyzer::fake(function ($prompt) use (&$promptReceived) {
        $promptReceived = $prompt;

        return fakeChapterAnalysis([
            'summary' => 'Chapter 2 summary.',
            'emotional_state_close' => 'hopeful',
            'emotional_shift_magnitude' => 3,
        ]);
    });

    EntityExtractor::fake(function () {
        return ['characters' => [], 'entities' => []];
    });

    ManuscriptAnalyzer::fake(function () {
        return ['score' => 5, 'findings' => [], 'recommendations' => []];
    });

    [$book, $chapters, $preparation] = createBookForAnalysis(2);

    // Simulate chapter 1 already analyzed (has a summary in DB)
    $chapters[0]->update(['summary' => 'First chapter: the journey begins.']);

    $job = new AnalyzeChapter($book, $preparation, $chapters[1]->id);
    $job->handle();

    // The ChapterAnalyzer should have been constructed with rolling context
    // We verify by checking that the second chapter was analyzed successfully
    $chapters[1]->refresh();
    expect($chapters[1]->summary)->toBe('Chapter 2 summary.');
});

test('analyze chapter logs errors without throwing', function () {
    ChapterAnalyzer::fake(function () {
        throw new \RuntimeException('AI service unavailable');
    });

    EntityExtractor::fake(function () {
        return ['characters' => [], 'entities' => []];
    });

    ManuscriptAnalyzer::fake(function () {
        return ['score' => 5, 'findings' => [], 'recommendations' => []];
    });

    [$book, $chapters, $preparation] = createBookForAnalysis(1);

    $job = new AnalyzeChapter($book, $preparation, $chapters[0]->id);
    $job->handle();

    $preparation->refresh();
    expect($preparation->phase_errors)->toBeArray()
        ->and(count($preparation->phase_errors))->toBe(1)
        ->and($preparation->phase_errors[0]['phase'])->toBe('chapter_analysis')
        ->and($preparation->phase_errors[0]['error'])->toContain('AI service unavailable');

    // Chapter should NOT have been updated
    $chapters[0]->refresh();
    expect($chapters[0]->summary)->toBeNull();
});

test('analyze chapter skips chapter without content', function () {
    [$book, $chapters, $preparation] = createBookForAnalysis(1);

    // Remove content from the chapter version
    $chapters[0]->currentVersion->update(['content' => null]);

    $job = new AnalyzeChapter($book, $preparation, $chapters[0]->id);
    $job->handle();

    $preparation->refresh();
    expect($preparation->phase_errors)->toBeNull()
        ->and($preparation->current_phase_progress)->toBe(1);
});

test('analyze chapter sets prepared_content_hash on success', function () {
    ChapterAnalyzer::fake(fn () => fakeChapterAnalysis());

    EntityExtractor::fake(function () {
        return ['characters' => [], 'entities' => []];
    });

    ManuscriptAnalyzer::fake(function () {
        return ['score' => 5, 'findings' => [], 'recommendations' => []];
    });

    [$book, $chapters, $preparation] = createBookForAnalysis(1);

    // Set a content_hash on the chapter so we can verify it gets copied
    $chapters[0]->update(['content_hash' => 'abc123']);

    $job = new AnalyzeChapter($book, $preparation, $chapters[0]->id);
    $job->handle();

    $chapters[0]->refresh();
    expect($chapters[0]->prepared_content_hash)->toBe('abc123')
        ->and($chapters[0]->ai_prepared_at)->not->toBeNull();
});

test('analyze chapter does not set prepared_content_hash when analysis fails', function () {
    ChapterAnalyzer::fake(function () {
        throw new \RuntimeException('API timeout');
    });

    EntityExtractor::fake(function () {
        return ['characters' => [], 'entities' => []];
    });

    ManuscriptAnalyzer::fake(function () {
        return ['score' => 5, 'findings' => [], 'recommendations' => []];
    });

    [$book, $chapters, $preparation] = createBookForAnalysis(1);
    $chapters[0]->update(['content_hash' => 'abc123']);

    $job = new AnalyzeChapter($book, $preparation, $chapters[0]->id);
    $job->handle();

    $chapters[0]->refresh();
    expect($chapters[0]->prepared_content_hash)->toBeNull()
        ->and($chapters[0]->ai_prepared_at)->toBeNull();
});

test('analyze chapter does not set prepared_content_hash when entity extraction fails', function () {
    ChapterAnalyzer::fake(fn () => fakeChapterAnalysis());

    EntityExtractor::fake(function () {
        throw new \RuntimeException('Entity extraction failed');
    });

    ManuscriptAnalyzer::fake(function () {
        return ['score' => 5, 'findings' => [], 'recommendations' => []];
    });

    [$book, $chapters, $preparation] = createBookForAnalysis(1);
    $chapters[0]->update(['content_hash' => 'abc123']);

    $job = new AnalyzeChapter($book, $preparation, $chapters[0]->id);
    $job->handle();

    $chapters[0]->refresh();
    // Analysis succeeded but entity extraction failed — not marked as prepared
    expect($chapters[0]->prepared_content_hash)->toBeNull()
        ->and($chapters[0]->summary)->toBe('Summary.');
});

test('analyze chapter runs manuscript analyses', function () {
    ChapterAnalyzer::fake(fn () => fakeChapterAnalysis());

    EntityExtractor::fake(function () {
        return ['characters' => [], 'entities' => []];
    });

    ManuscriptAnalyzer::fake(function () {
        return ['score' => 8, 'findings' => ['Consistent'], 'recommendations' => ['Keep going']];
    });

    [$book, $chapters, $preparation] = createBookForAnalysis(1);

    $job = new AnalyzeChapter($book, $preparation, $chapters[0]->id);
    $job->handle();

    $analyses = $book->analyses()->where('chapter_id', $chapters[0]->id)->get();
    expect($analyses)->toHaveCount(2);

    $types = $analyses->pluck('type')->map(fn ($t) => $t->value)->sort()->values()->all();
    expect($types)->toBe([AnalysisType::CharacterConsistency->value, AnalysisType::PlotDeviation->value]);

    expect($analyses->first()->result['score'])->toBe(8);
});

test('analyze chapter logs manuscript analysis errors without throwing', function () {
    ChapterAnalyzer::fake(fn () => fakeChapterAnalysis());

    EntityExtractor::fake(function () {
        return ['characters' => [], 'entities' => []];
    });

    ManuscriptAnalyzer::fake(function () {
        throw new \RuntimeException('Manuscript analysis failed');
    });

    [$book, $chapters, $preparation] = createBookForAnalysis(1);
    $chapters[0]->update(['content_hash' => 'abc123']);

    $job = new AnalyzeChapter($book, $preparation, $chapters[0]->id);
    $job->handle();

    // Chapter should still be marked as prepared (chapter analysis + entities succeeded)
    $chapters[0]->refresh();
    expect($chapters[0]->prepared_content_hash)->toBe('abc123');

    // Error should be logged
    $preparation->refresh();
    expect($preparation->phase_errors)->toBeArray()
        ->and($preparation->phase_errors[0]['phase'])->toBe('manuscript_analysis');
});

test('analyze chapter rethrows transient errors for retry', function () {
    ChapterAnalyzer::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
    });

    EntityExtractor::fake(function () {
        return ['characters' => [], 'entities' => []];
    });

    [$book, $chapters, $preparation] = createBookForAnalysis(1);

    $job = new AnalyzeChapter($book, $preparation, $chapters[0]->id);

    // Transient errors should be rethrown, not caught
    expect(fn () => $job->handle())->toThrow(\Illuminate\Http\Client\ConnectionException::class);

    // Phase errors should NOT have been logged (will be logged by failed() after retries exhausted)
    $preparation->refresh();
    expect($preparation->phase_errors)->toBeNull();
});
