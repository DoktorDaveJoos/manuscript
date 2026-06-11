<?php

use App\Ai\Agents\EditorialNotesAgent;
use App\Ai\Agents\EditorialSummaryAgent;
use App\Ai\Agents\EditorialSynthesisAgent;
use App\Models\AppSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\License;
use App\Models\Scene;
use App\Models\Storyline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // AppSetting/License caches are reset in TestCase::setUp(); only the
        // locale needs to be re-applied here since it's request state.
        app()->setLocale(config('app.locale', 'en'));

        // Global RequiresLicense middleware redirects every request to the
        // welcome page when no license is active. Most feature tests assume a
        // licensed app, so seed one by default. Tests covering the unlicensed
        // path (gate redirect, welcome page, activation flow) call
        // License::query()->delete() + License::clearActiveCache() up front.
        License::factory()->create();
    })
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // TestCase::setUp() calls withoutVite() so Feature/Unit runs need no
        // built assets — but browser tests drive a real browser, which needs
        // the real @vite script tags or every page sits on the static loader
        // forever. Restore the binding here. Run `npm run build` first (and
        // make sure no Vite dev server / public/hot file is interfering).
        $this->withVite();

        AppSetting::set('crash_report_prompted', true);
        AppSetting::set('language_prompted', true);

        // Browser tests boot the full app — same reasoning as Feature: assume
        // licensed by default.
        License::factory()->create();
    })
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Drop the license seeded by Pest's beforeEach hook so tests can exercise
 * the unlicensed path through the global RequiresLicense middleware.
 */
function clearLicense(): void
{
    License::query()->delete();
    License::clearActiveCache();
}

function createBookWithChapters(int $chapterCount = 3): array
{
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapters = [];

    for ($i = 1; $i <= $chapterCount; $i++) {
        $content = "<p>Chapter {$i} content. ".fake()->paragraphs(3, true).'</p>';
        $chapter = Chapter::factory()->for($book)->for($storyline)->create([
            'reader_order' => $i,
            'title' => "Chapter {$i}",
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
        $chapter->refreshContentHash();
        $chapters[] = $chapter;
    }

    return [$book, $chapters];
}

/**
 * Build a book with prepared (non-stale) chapters for editorial review tests.
 *
 * @return array{0: Book, 1: list<Chapter>}
 */
function createBookWithChaptersForEditorial(int $chapterCount = 3): array
{
    [$book, $chapters] = createBookWithChapters($chapterCount);

    // Mark every chapter prepared (prepared_content_hash === content_hash) so
    // editorial review treats them as non-stale and skips Phase 0 refresh.
    foreach ($chapters as $chapter) {
        $chapter->update(['prepared_content_hash' => $chapter->content_hash]);
    }

    // A prepared book also has a writing style — without one, dispatching a
    // review would batch a RefreshWritingStyleJob (a real AI call on sync queues).
    $book->update(['writing_style' => ['tone' => 'measured and wry']]);

    return [$book, $chapters];
}

/**
 * Fake the three editorial agents (notes, synthesis, summary) with valid shapes.
 * ChapterAnalyzer is faked per-test where stale-refresh behaviour is exercised.
 */
function fakeAllEditorialAgents(): void
{
    EditorialNotesAgent::fake(fn () => [
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
    ]);

    EditorialSynthesisAgent::fake(fn () => [
        'score' => 72,
        'summary' => 'The section shows strong fundamentals with room for improvement.',
        'strengths' => ['The midpoint reversal lands because it was set up in chapter 2.'],
        'findings' => [
            [
                'severity' => 'warning',
                'description' => 'Minor inconsistency detected.',
                'chapter_references' => [1],
                'recommendation' => 'Review the passage for consistency.',
            ],
        ],
        'recommendations' => ['Tighten prose in action scenes.'],
    ]);

    EditorialSummaryAgent::fake(fn () => [
        'overall_score' => 75,
        'executive_summary' => 'This is a solid manuscript with clear strengths in character development.',
        'top_strengths' => ['Strong characters', 'Compelling plot', 'Vivid settings'],
        'top_improvements' => ['Pacing in middle act', 'Dialogue tags', 'Tighter prose'],
        'is_pre_editorial' => false,
    ]);
}
