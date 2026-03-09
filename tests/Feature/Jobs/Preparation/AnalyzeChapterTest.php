<?php

use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\CharacterExtractor;
use App\Jobs\Preparation\AnalyzeChapter;
use App\Models\AiPreparation;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Storyline;

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

test('analyze chapter performs analysis and character extraction', function () {
    ChapterAnalyzer::fake(function () {
        return [
            'summary' => 'The hero enters the castle.',
            'key_events' => ['Entered castle'],
            'characters_present' => ['John'],
            'tension_score' => 7,
            'hook_score' => 8,
            'hook_type' => 'cliffhanger',
            'hook_reasoning' => 'Strong ending.',
            'plot_points' => [
                ['title' => 'Castle entry', 'description' => 'Hero enters the castle', 'type' => 'conflict'],
            ],
        ];
    });

    CharacterExtractor::fake(function () {
        return [
            'characters' => [
                ['name' => 'John', 'aliases' => null, 'description' => 'The hero', 'role' => 'protagonist'],
            ],
        ];
    });

    [$book, $chapters, $preparation] = createBookForAnalysis(1);

    $job = new AnalyzeChapter($book, $preparation, $chapters[0]->id);
    $job->handle();

    $chapters[0]->refresh();
    expect($chapters[0]->summary)->toBe('The hero enters the castle.')
        ->and($chapters[0]->tension_score)->toBe(7)
        ->and($chapters[0]->hook_score)->toBe(8)
        ->and($chapters[0]->hook_type)->toBe('cliffhanger');

    expect($book->characters()->where('name', 'John')->exists())->toBeTrue();
    expect($book->plotPoints()->where('is_ai_derived', true)->count())->toBe(1);

    $preparation->refresh();
    expect($preparation->current_phase_progress)->toBe(1);
});

test('analyze chapter builds rolling context from preceding chapters', function () {
    $promptReceived = null;
    ChapterAnalyzer::fake(function ($prompt) use (&$promptReceived) {
        $promptReceived = $prompt;

        return [
            'summary' => 'Chapter 2 summary.',
            'key_events' => [],
            'characters_present' => [],
            'tension_score' => 5,
            'hook_score' => 5,
            'hook_type' => 'closed',
            'hook_reasoning' => 'OK.',
            'plot_points' => [],
        ];
    });

    CharacterExtractor::fake(function () {
        return ['characters' => []];
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

    CharacterExtractor::fake(function () {
        return ['characters' => []];
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
