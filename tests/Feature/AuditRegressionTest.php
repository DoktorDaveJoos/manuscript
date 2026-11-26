<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Chunk;
use App\Models\EditorialReview;
use App\Models\License;
use App\Models\Scene;
use App\Models\Storyline;
use App\Models\WritingSession;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => License::factory()->create());

it('cancels active Bus batches when EditorialReview::failStale marks a review failed', function () {
    $book = Book::factory()->create();
    $batch = Bus::batch([])->dispatch();

    $review = EditorialReview::factory()->for($book)->create([
        'status' => 'analyzing',
        'batch_id' => $batch->id,
        'updated_at' => now()->subMinutes(EditorialReview::STALE_AFTER_MINUTES + 5),
    ]);

    $count = EditorialReview::failStale($book);

    expect($count)->toBe(1)
        ->and($review->fresh()->status)->toBe('failed')
        ->and(Bus::findBatch($batch->id)->cancelled())->toBeTrue();
});

it('samples live scenes in Book::writingStyleSample and skips empty Tiptap paragraphs', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    // First 3 chapters have empty Tiptap '<p></p>' scenes; chapter 4 holds real prose
    // only in scenes (currentVersion->content is empty, matching editor-authored chapters).
    for ($i = 1; $i <= 3; $i++) {
        $emptyChapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => $i]);
        ChapterVersion::factory()->for($emptyChapter)->create(['is_current' => true, 'content' => '']);
        Scene::factory()->for($emptyChapter)->create(['content' => '<p></p>', 'sort_order' => 0]);
    }

    $proseChapter = Chapter::factory()->for($book)->for($storyline)->create(['reader_order' => 4]);
    ChapterVersion::factory()->for($proseChapter)->create(['is_current' => true, 'content' => '']);
    Scene::factory()->for($proseChapter)->create([
        'content' => '<p>'.trim(str_repeat('starlight ', 350)).'</p>',
        'sort_order' => 0,
    ]);

    $sample = $book->writingStyleSample();

    expect($sample)->not->toBeNull()
        ->and($sample)->toContain('starlight');
});

it('excludes non-current versions and soft-deleted chapters from FTS5 keyword search', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $activeChapter = Chapter::factory()->for($book)->for($storyline)->create();
    $oldVersion = ChapterVersion::factory()->for($activeChapter)->create(['is_current' => false, 'version_number' => 1]);
    $currentVersion = ChapterVersion::factory()->for($activeChapter)->create(['is_current' => true, 'version_number' => 2]);

    Chunk::factory()->for($oldVersion)->create(['content' => 'obsolete_zephyr_token in old draft']);
    $liveChunk = Chunk::factory()->for($currentVersion)->create(['content' => 'current_zephyr_token in live draft']);

    $deletedChapter = Chapter::factory()->for($book)->for($storyline)->create();
    $deletedVersion = ChapterVersion::factory()->for($deletedChapter)->create(['is_current' => true]);
    Chunk::factory()->for($deletedVersion)->create(['content' => 'trashed_zephyr_token in deleted chapter']);
    $deletedChapter->delete();

    expect(Chunk::findByKeywordForBook($book->id, 'obsolete_zephyr_token'))->toBeEmpty()
        ->and(Chunk::findByKeywordForBook($book->id, 'trashed_zephyr_token'))->toBeEmpty()
        ->and(Chunk::findByKeywordForBook($book->id, 'current_zephyr_token')->pluck('id')->all())->toBe([$liveChunk->id]);
});

it('matches today writing_sessions stored with midnight timestamps on dashboard', function () {
    $book = Book::factory()->create(['daily_word_count_goal' => 500]);
    Storyline::factory()->for($book)->create();

    WritingSession::create([
        'book_id' => $book->id,
        'date' => now()->toDateString().' 00:00:00',
        'words_written' => 750,
        'goal_met' => true,
    ]);

    $this->get(route('books.dashboard', $book))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('writing_goal.today_words', 750)
            ->where('writing_goal.goal_met_today', true)
            ->where('writing_goal.streak', 1)
        );
});

it('defaults SQLite connections to IMMEDIATE transaction mode including dynamic nativephp connections', function () {
    config([
        'database.connections.nativephp_test_probe' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ],
    ]);

    $connection = DB::connection('nativephp_test_probe');

    expect($connection->getConfig('transaction_mode'))->toBe('IMMEDIATE');
});
