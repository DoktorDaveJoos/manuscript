<?php

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Scene;
use App\Models\Storyline;

beforeEach(function () {
    $this->book = Book::factory()->create();
    $this->storyline = Storyline::factory()->for($this->book)->create();
    $this->chapter = Chapter::factory()->for($this->book)->for($this->storyline)->create([
        'title' => 'The Morning After',
        'reader_order' => 1,
    ]);
    $this->scene = Scene::factory()->for($this->chapter)->create([
        'title' => 'Waking Up',
        'content' => '<p>The morning sun crept through the curtains. By morning, the coffee was ready.</p><p>She loved every morning routine.</p>',
        'sort_order' => 0,
        'word_count' => 20,
    ]);
});

test('search finds matches across scenes', function () {
    $response = $this->postJson("/books/{$this->book->id}/search", [
        'query' => 'morning',
    ]);

    $response->assertOk();
    $response->assertJsonPath('total_matches', 3);
    $response->assertJsonPath('chapter_count', 1);
    $response->assertJsonPath('results.0.chapter_id', $this->chapter->id);
    $response->assertJsonPath('results.0.chapter_title', 'The Morning After');
    $response->assertJsonCount(3, 'results.0.matches');
});

test('search is case insensitive by default', function () {
    $response = $this->postJson("/books/{$this->book->id}/search", [
        'query' => 'Morning',
    ]);

    $response->assertOk();
    $response->assertJsonPath('total_matches', 3);
});

test('case sensitive search filters correctly', function () {
    $response = $this->postJson("/books/{$this->book->id}/search", [
        'query' => 'The morning',
        'case_sensitive' => true,
    ]);

    $response->assertOk();
    $response->assertJsonPath('total_matches', 1);
});

test('whole word search filters correctly', function () {
    $this->scene->update([
        'content' => '<p>The morning was calm. Good mornings are rare.</p>',
    ]);

    $response = $this->postJson("/books/{$this->book->id}/search", [
        'query' => 'morning',
        'whole_word' => true,
    ]);

    $response->assertOk();
    $response->assertJsonPath('total_matches', 1);
});

test('search returns empty for no matches', function () {
    $response = $this->postJson("/books/{$this->book->id}/search", [
        'query' => 'nonexistent',
    ]);

    $response->assertOk();
    $response->assertJsonPath('total_matches', 0);
    $response->assertJsonPath('results', []);
});

test('search requires a query', function () {
    $response = $this->postJson("/books/{$this->book->id}/search", []);

    $response->assertUnprocessable();
});

test('replace all replaces content in HTML text nodes', function () {
    $response = $this->postJson("/books/{$this->book->id}/search/replace-all", [
        'search' => 'morning',
        'replace' => 'evening',
    ]);

    $response->assertOk();
    $response->assertJsonPath('replaced_count', 3);
    $response->assertJsonPath('affected_scenes', 1);

    $this->scene->refresh();
    expect($this->scene->content)->toContain('evening');
    expect($this->scene->content)->not->toContain('morning');
    // HTML structure preserved
    expect($this->scene->content)->toContain('<p>');
    expect($this->scene->content)->toContain('</p>');
});

test('replace all updates word count', function () {
    $oldWordCount = $this->scene->word_count;

    $this->postJson("/books/{$this->book->id}/search/replace-all", [
        'search' => 'morning',
        'replace' => 'beautiful evening',
    ]);

    $this->scene->refresh();
    $this->chapter->refresh();

    expect($this->scene->word_count)->toBeGreaterThan($oldWordCount);
});

test('search across multiple chapters', function () {
    $chapter2 = Chapter::factory()->for($this->book)->for($this->storyline)->create([
        'title' => 'Crossroads',
        'reader_order' => 2,
    ]);
    Scene::factory()->for($chapter2)->create([
        'title' => 'The Decision',
        'content' => '<p>That morning she decided to leave.</p>',
        'sort_order' => 0,
        'word_count' => 7,
    ]);

    $response = $this->postJson("/books/{$this->book->id}/search", [
        'query' => 'morning',
    ]);

    $response->assertOk();
    $response->assertJsonPath('total_matches', 4);
    $response->assertJsonPath('chapter_count', 2);
});
