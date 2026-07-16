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
    $response->assertJsonPath('affected_chapter_ids', [$this->chapter->id]);

    $this->scene->refresh();
    expect($this->scene->content)->toContain('evening');
    expect($this->scene->content)->not->toContain('morning');
    expect($this->scene->content_version)->toBe(1);
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

test('search does not match words joined across paragraph boundaries', function () {
    // "to" ends one paragraph and "ward" starts the next — a naive
    // strip_tags would glue them into a phantom "toward".
    $this->scene->update([
        'content' => '<p>He walked to</p><p>ward the door she went toward the light.</p>',
    ]);

    $response = $this->postJson("/books/{$this->book->id}/search", [
        'query' => 'toward',
    ]);

    $response->assertOk();
    $response->assertJsonPath('total_matches', 1);
});

test('search does not match words joined across line breaks', function () {
    $this->scene->update([
        'content' => '<p>He walked to<br>ward the door she went toward the light.</p>',
    ]);

    $response = $this->postJson("/books/{$this->book->id}/search", [
        'query' => 'toward',
    ]);

    $response->assertOk();
    $response->assertJsonPath('total_matches', 1);
});

test('search decodes HTML entities and normalizes non-breaking spaces', function () {
    $this->scene->update([
        'content' => '<p>Rock &amp; roll&nbsp;forever.</p>',
    ]);

    $this->postJson("/books/{$this->book->id}/search", [
        'query' => '&',
    ])->assertOk()->assertJsonPath('total_matches', 1);

    $this->postJson("/books/{$this->book->id}/search", [
        'query' => 'roll forever',
    ])->assertOk()->assertJsonPath('total_matches', 1);

    $this->scene->update(['content' => '<p>The literal text &amp;amp; stays searchable.</p>']);
    $this->postJson("/books/{$this->book->id}/search", [
        'query' => '&amp;',
    ])->assertOk()->assertJsonPath('total_matches', 1);
});

test('literal replacement preserves dollar signs and escapes HTML', function () {
    $this->scene->update([
        'content' => '<p>morning &amp; morning</p>',
    ]);

    $response = $this->postJson("/books/{$this->book->id}/search/replace-all", [
        'search' => 'morning',
        'replace' => '$1 <strong>unsafe</strong> &',
    ]);

    $response->assertOk()->assertJsonPath('replaced_count', 2);

    expect($this->scene->fresh()->content)
        ->toBe('<p>$1 &lt;strong&gt;unsafe&lt;/strong&gt; &amp; &amp; $1 &lt;strong&gt;unsafe&lt;/strong&gt; &amp;</p>')
        ->not->toContain('<strong>unsafe</strong>');
});

test('replacement never changes tag attributes containing angle brackets', function () {
    $this->scene->update([
        'content' => '<p><a title="morning > dawn">morning</a></p>',
    ]);

    $response = $this->postJson("/books/{$this->book->id}/search/replace-all", [
        'search' => 'morning',
        'replace' => 'evening',
    ]);

    $response->assertOk()->assertJsonPath('replaced_count', 1);
    expect($this->scene->fresh()->content)
        ->toBe('<p><a title="morning > dawn">evening</a></p>');
});

test('regex replacement expands captures and accepts slash delimiters', function () {
    $this->scene->update([
        'content' => '<p>morning path/to morning</p>',
    ]);

    $this->postJson("/books/{$this->book->id}/search", [
        'query' => 'path/to',
        'regex' => true,
    ])->assertOk()->assertJsonPath('total_matches', 1);

    $response = $this->postJson("/books/{$this->book->id}/search/replace-all", [
        'search' => '(morn)(ing)',
        'replace' => '$2-$1',
        'regex' => true,
    ]);

    $response->assertOk()->assertJsonPath('replaced_count', 2);
    expect($this->scene->fresh()->content)->toBe('<p>ing-morn path/to ing-morn</p>');
});

test('invalid regular expressions return a validation error', function () {
    $this->postJson("/books/{$this->book->id}/search", [
        'query' => '[',
        'regex' => true,
    ])->assertUnprocessable()->assertJsonPath('code', 'invalid_regex');

    $this->postJson("/books/{$this->book->id}/search/replace-all", [
        'search' => '[',
        'replace' => 'x',
        'regex' => true,
    ])->assertUnprocessable()->assertJsonPath('code', 'invalid_regex');
});

test('search and replacement use the same trimmed query', function () {
    $searchResponse = $this->postJson("/books/{$this->book->id}/search", [
        'query' => '  morning  ',
    ]);

    $searchResponse->assertOk()->assertJsonPath('total_matches', 3);

    $replaceResponse = $this->postJson("/books/{$this->book->id}/search/replace-all", [
        'search' => '  morning  ',
        'replace' => 'evening',
    ]);

    $replaceResponse->assertOk()->assertJsonPath('replaced_count', 3);
});
