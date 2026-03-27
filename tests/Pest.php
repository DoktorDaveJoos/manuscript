<?php

use App\Models\AiPreparation;
use App\Models\AppSetting;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
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
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        AppSetting::set('crash_report_prompted', true);
        AppSetting::set('language_prompted', true);
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

    $preparation = AiPreparation::create([
        'book_id' => $book->id,
        'status' => 'pending',
    ]);

    return [$book, $chapters, $preparation];
}
