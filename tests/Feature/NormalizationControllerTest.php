<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Scene;
use App\Models\Storyline;

it('previews book normalization and returns changes', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $response = $this->postJson(route('books.normalize.preview', $book));

    $response->assertOk()
        ->assertJsonStructure([
            'chapters',
            'total_changes',
        ]);
});

it('applies book normalization', function () {
    [$book, $chapters] = createBookWithChapters(2);

    $response = $this->postJson(route('books.normalize.apply', $book));

    $response->assertOk()
        ->assertJsonStructure(['applied_chapters']);

    expect($response->json('applied_chapters'))->toBeGreaterThanOrEqual(0);
});

it('previews chapter normalization', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];

    $response = $this->postJson(route('chapters.normalize.preview', [$book, $chapter]));

    $response->assertOk()
        ->assertJsonStructure([
            'chapters',
            'total_changes',
        ]);
});

it('applies chapter normalization', function () {
    [$book, $chapters] = createBookWithChapters(1);
    $chapter = $chapters[0];

    $response = $this->postJson(route('chapters.normalize.apply', [$book, $chapter]));

    $response->assertOk()
        ->assertJsonStructure(['applied']);
});

it('returns empty changes for chapter without content', function () {
    $book = Book::factory()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    // Chapter has no version/content
    $response = $this->postJson(route('chapters.normalize.preview', [$book, $chapter]));

    $response->assertOk()
        ->assertJson([
            'chapters' => [],
            'total_changes' => 0,
        ]);
});

it('preserves multi-scene structure and titles when applying normalization', function () {
    $book = Book::factory()->create(['language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create(['is_current' => true, 'content' => '']);
    $sceneA = Scene::factory()->for($chapter)->create([
        'title' => 'Arrival',
        'content' => '<p>He said "hello"  there...</p>',
        'sort_order' => 0,
    ]);
    $sceneB = Scene::factory()->for($chapter)->create([
        'title' => 'Departure',
        'content' => '<p>She replied "goodbye"...</p>',
        'sort_order' => 1,
    ]);

    // Preview should inspect live scenes even when currentVersion is empty
    $preview = $this->postJson(route('chapters.normalize.preview', [$book, $chapter]))
        ->assertOk();
    expect($preview->json('total_changes'))->toBeGreaterThan(0);

    $this->postJson(route('chapters.normalize.apply', [$book, $chapter]))
        ->assertOk()
        ->assertJson(['applied' => true]);

    $scenes = $chapter->scenes()->orderBy('sort_order')->get();
    expect($scenes)->toHaveCount(2)
        ->and($scenes[0]->id)->toBe($sceneA->id)
        ->and($scenes[0]->title)->toBe('Arrival')
        ->and($scenes[1]->id)->toBe($sceneB->id)
        ->and($scenes[1]->title)->toBe('Departure');
});

it('normalizes scene-less chapters with currentVersion content consistently between preview and apply', function () {
    $book = Book::factory()->create(['language' => 'en']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'content' => '<p>He said "hello"  there...</p>',
    ]);

    $preview = $this->postJson(route('chapters.normalize.preview', [$book, $chapter]))
        ->assertOk();
    expect($preview->json('total_changes'))->toBeGreaterThan(0);

    $this->postJson(route('chapters.normalize.apply', [$book, $chapter]))
        ->assertOk()
        ->assertJson(['applied' => true]);

    expect($chapter->scenes()->count())->toBe(1);
});

it('returns 404 when normalizing a chapter belonging to another book', function () {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $storylineB = Storyline::factory()->for($bookB)->create();
    $chapterB = Chapter::factory()->for($bookB)->for($storylineB)->create();

    $this->postJson(route('chapters.normalize.preview', [$bookA, $chapterB]))->assertNotFound();
    $this->postJson(route('chapters.normalize.apply', [$bookA, $chapterB]))->assertNotFound();
});
