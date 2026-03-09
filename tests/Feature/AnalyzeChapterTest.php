<?php

use App\Ai\Agents\BookChatAgent;
use App\Ai\Agents\ChapterAnalyzer;
use App\Ai\Agents\EntityExtractor;
use App\Ai\Agents\ManuscriptAnalyzer;
use App\Enums\AiProvider;
use App\Jobs\AnalyzeChapterJob;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\License;
use App\Models\Storyline;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    License::factory()->create();
});

// --- Endpoint tests ---

test('analyzeChapter dispatches AnalyzeChapterJob and sets status to pending', function () {
    Queue::fake();

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(route('chapters.ai.analyzeChapter', [$book, $chapter]))
        ->assertOk()
        ->assertJsonPath('status', 'pending');

    Queue::assertPushed(AnalyzeChapterJob::class);

    expect($chapter->fresh()->analysis_status)->toBe('pending');
});

test('chapterAnalysisStatus returns analysis data', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'analysis_status' => 'completed',
        'analyzed_at' => now(),
        'tension_score' => 7,
        'hook_score' => 8,
        'hook_type' => 'cliffhanger',
        'summary' => 'A test summary.',
    ]);

    $book->analyses()->create([
        'chapter_id' => $chapter->id,
        'type' => 'pacing',
        'result' => ['score' => 7, 'findings' => ['Good pacing'], 'recommendations' => []],
        'ai_generated' => true,
    ]);

    $this->getJson(route('chapters.ai.analysisStatus', [$book, $chapter]))
        ->assertOk()
        ->assertJsonPath('analysis_status', 'completed')
        ->assertJsonPath('tension_score', 7)
        ->assertJsonPath('hook_score', 8)
        ->assertJsonPath('summary', 'A test summary.')
        ->assertJsonPath('analyses.pacing.result.score', 7);
});

test('analyzeChapter requires license', function () {
    License::query()->delete();

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(route('chapters.ai.analyzeChapter', [$book, $chapter]))
        ->assertForbidden();
});

// --- Job tests ---

test('AnalyzeChapterJob runs chapter analysis and updates chapter', function () {
    ChapterAnalyzer::fake(function () {
        return [
            'summary' => 'A test chapter summary.',
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
            'plot_points' => [
                ['title' => 'Plot point 1', 'description' => 'Something happened', 'type' => 'conflict'],
            ],
        ];
    });
    EntityExtractor::fake(function () {
        return ['characters' => [['name' => 'John', 'aliases' => [], 'description' => 'Main character']], 'entities' => []];
    });
    ManuscriptAnalyzer::fake(function () {
        return ['score' => 7, 'findings' => ['Good'], 'recommendations' => ['Keep going']];
    });

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'analysis_status' => 'pending',
    ]);
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => '<p>Some chapter content for analysis.</p>',
    ]);

    $job = new AnalyzeChapterJob($book, $chapter);
    $job->handle();

    $chapter->refresh();

    expect($chapter->analysis_status)->toBe('completed')
        ->and($chapter->analyzed_at)->not->toBeNull()
        ->and($chapter->summary)->toBe('A test chapter summary.')
        ->and($chapter->tension_score)->toBe(7)
        ->and($chapter->hook_score)->toBe(8)
        ->and($chapter->hook_type)->toBe('cliffhanger')
        ->and($chapter->scene_purpose)->toBe('turning_point')
        ->and($chapter->value_shift)->toBe('safety → danger')
        ->and($chapter->micro_tension_score)->toBe(6)
        ->and($chapter->pacing_feel)->toBe('brisk')
        ->and($chapter->entry_hook_score)->toBe(7)
        ->and($chapter->exit_hook_score)->toBe(8)
        ->and($chapter->sensory_grounding)->toBe(4)
        ->and($chapter->information_delivery)->toBe('organic');

    // Character was created
    expect($book->characters()->where('name', 'John')->exists())->toBeTrue();

    // Analyses were stored (2 ManuscriptAnalyzer types: CharacterConsistency + PlotDeviation)
    expect($book->analyses()->where('chapter_id', $chapter->id)->count())->toBe(2);
});

test('AnalyzeChapterJob marks chapter as failed when no AI provider configured', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'analysis_status' => 'pending',
    ]);

    $job = new AnalyzeChapterJob($book, $chapter);
    $job->handle();

    expect($chapter->fresh()->analysis_status)->toBe('failed')
        ->and($chapter->fresh()->analysis_error)->toBe('No AI provider configured.');
});

// --- Chat endpoint tests ---

test('chat streams response from BookChatAgent', function () {
    BookChatAgent::fake(['This is the AI response about the book.']);

    $book = Book::factory()->withAi()->create();

    $response = $this->post(route('books.ai.chat', $book), [
        'message' => 'What happens in chapter 1?',
    ]);
    $response->assertOk();

    BookChatAgent::assertPrompted(fn ($prompt) => true);
});

test('chat validates message is required', function () {
    $book = Book::factory()->withAi()->create();

    $this->postJson(route('books.ai.chat', $book), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

test('chat fails without AI configured', function () {
    $book = Book::factory()->create();
    AiSetting::factory()->withoutKey()->create([
        'provider' => AiProvider::Anthropic,
        'enabled' => true,
    ]);

    $this->postJson(route('books.ai.chat', $book), ['message' => 'Hello'])
        ->assertStatus(422);
});
