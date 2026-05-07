<?php

use App\Ai\Agents\ContinueWritingAgent;
use App\Enums\AiProvider;
use App\Enums\VersionSource;
use App\Enums\VersionStatus;
use App\Models\AiSetting;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\ChapterVersion;
use App\Models\Character;
use App\Models\License;
use App\Models\PlotPoint;
use App\Models\Scene;
use App\Models\Storyline;
use App\Models\WikiEntry;

beforeEach(function () {
    License::factory()->create();
});

test('continue writing streams a paragraph as SSE', function () {
    ContinueWritingAgent::fake(['She turned away from the window. ']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create([
        'content' => '<p>The room was quiet.</p>',
        'sort_order' => 0,
    ]);

    $response = $this->post(
        route('chapters.ai.continueWriting', [$book, $chapter]),
        ['hint' => '', 'word_goal' => 60],
    );

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $body = $response->streamedContent();
    expect($body)->toContain('[DONE]');

    $combined = '';
    foreach (preg_split('/\r?\n/', $body) as $line) {
        if (! str_starts_with($line, 'data: ')) {
            continue;
        }
        $payload = substr($line, 6);
        if ($payload === '[DONE]') {
            continue;
        }
        $decoded = json_decode($payload, true);
        $combined .= $decoded['delta'];
    }

    expect($combined)->toBe('She turned away from the window. ');

    ContinueWritingAgent::assertPrompted(fn ($prompt) => true);
});

test('continue writing forwards the hint to the agent', function () {
    ContinueWritingAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $this->post(
        route('chapters.ai.continueWriting', [$book, $chapter]),
        ['hint' => 'Make it tense, focus on Anna', 'word_goal' => 80],
    )->assertOk();

    $agent = new ContinueWritingAgent($book, $chapter, 'Make it tense, focus on Anna', 80);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('AUTHOR NOTE')
        ->toContain('Make it tense, focus on Anna')
        ->toContain('approximately 80 words');
});

test('continue writing rejects invalid word goals', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(
        route('chapters.ai.continueWriting', [$book, $chapter]),
        ['word_goal' => 5],
    )->assertStatus(422);

    $this->postJson(
        route('chapters.ai.continueWriting', [$book, $chapter]),
        ['word_goal' => 9000],
    )->assertStatus(422);
});

test('continue writing rejects an over-long hint', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(
        route('chapters.ai.continueWriting', [$book, $chapter]),
        ['hint' => str_repeat('a', 1001)],
    )->assertStatus(422);
});

test('continue writing fails when no AI provider is configured', function () {
    $book = Book::factory()->create();
    AiSetting::factory()->withoutKey()->create([
        'provider' => AiProvider::Anthropic,
        'enabled' => true,
    ]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(
        route('chapters.ai.continueWriting', [$book, $chapter]),
    )->assertStatus(422);
});

test('continue writing 404s when chapter does not belong to the book', function () {
    ContinueWritingAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $otherBook = Book::factory()->create();
    $storyline = Storyline::factory()->for($otherBook)->create();
    $chapter = Chapter::factory()->for($otherBook)->for($storyline)->create();

    $this->post(
        route('chapters.ai.continueWriting', [$book, $chapter]),
    )->assertNotFound();
});

test('agent instructions include beats, characters, wiki entries, and chapter prose', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create(['name' => 'Main arc']);
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 2,
        'title' => 'Confrontation',
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>Anna stood in the doorway.</p>',
        'sort_order' => 0,
    ]);

    $plotPoint = PlotPoint::factory()->for($book)->create(['title' => 'Mother returns']);
    $beat = Beat::factory()->for($plotPoint)->create([
        'title' => 'Anna confronts her mother',
        'description' => 'The truth about the inheritance comes out.',
    ]);
    $chapter->beats()->attach($beat, ['sort_order' => 0]);

    $character = Character::factory()->for($book)->create([
        'name' => 'Anna',
        'description' => 'The protagonist.',
    ]);
    $chapter->characters()->attach($character, ['role' => 'POV']);

    $wiki = WikiEntry::factory()->for($book)->location()->create([
        'name' => 'The library',
        'description' => 'A dusty room of books.',
    ]);
    $chapter->wikiEntries()->attach($wiki);

    $agent = new ContinueWritingAgent($book, $chapter, null, 120);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Anna confronts her mother')
        ->toContain('inheritance')
        ->toContain('Anna')
        ->toContain('POV')
        ->toContain('The library')
        ->toContain('Locations')
        ->toContain('Confrontation')
        ->toContain('Anna stood in the doorway')
        ->toContain('approximately 120 words');
});

test('agent instructions handle a chapter with no prose yet', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'title' => 'Opening',
    ]);

    $agent = new ContinueWritingAgent($book, $chapter, null, 120);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Opening')
        ->toContain('no prose written yet');
});

test('commit snapshots scenes into a new accepted current version', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $previous = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 3,
        'content' => '<p>The room was quiet.</p>',
        'is_current' => true,
        'status' => VersionStatus::Accepted,
        'source' => VersionSource::Original,
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>The room was quiet.</p><p>She turned away from the window.</p>',
        'sort_order' => 0,
    ]);

    $response = $this->postJson(
        route('chapters.ai.continueWriting.commit', [$book, $chapter]),
    )->assertOk();

    $payload = $response->json();
    expect($payload['previous']['id'])->toBe($previous->id);
    expect($payload['previous']['version_number'])->toBe(3);
    expect($payload['new']['version_number'])->toBe(4);
    expect($payload['new']['source'])->toBe('continue_writing');
    expect($payload['new']['status'])->toBe('accepted');

    expect($previous->fresh()->is_current)->toBeFalse();
    $new = ChapterVersion::find($payload['new']['id']);
    expect($new->is_current)->toBeTrue();
    expect($new->content)->toContain('She turned away from the window.');
});

test('commit creates the first version when no current version exists', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create([
        'content' => '<p>Fresh paragraph from the AI.</p>',
        'sort_order' => 0,
    ]);

    $response = $this->postJson(
        route('chapters.ai.continueWriting.commit', [$book, $chapter]),
    )->assertOk();

    expect($response->json('previous'))->toBeNull();
    expect($response->json('new.version_number'))->toBe(1);
    expect($response->json('new.source'))->toBe('continue_writing');
});

test('commit 404s when chapter does not belong to the book', function () {
    $book = Book::factory()->withAi()->create();
    $otherBook = Book::factory()->create();
    $storyline = Storyline::factory()->for($otherBook)->create();
    $chapter = Chapter::factory()->for($otherBook)->for($storyline)->create();

    $this->postJson(
        route('chapters.ai.continueWriting.commit', [$book, $chapter]),
    )->assertNotFound();
});

test('refine creates a new accepted version with the merged content', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $base = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 5,
        'content' => '<p>One.</p><p>Two.</p><p>Three.</p>',
        'is_current' => true,
        'status' => VersionStatus::Accepted,
        'source' => VersionSource::ContinueWriting,
        'scene_map' => [['title' => 'Scene 1', 'sort_order' => 0]],
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>One.</p><p>Two.</p><p>Three.</p>',
        'sort_order' => 0,
    ]);

    $merged = '<p>One.</p><p>Three.</p>';

    $response = $this->postJson(
        route('chapters.ai.continueWriting.refine', [$book, $chapter, $base]),
        ['content' => $merged],
    )->assertOk();

    expect($response->json('new.version_number'))->toBe(6);
    expect($response->json('new.source'))->toBe('continue_writing');
    expect($response->json('new.status'))->toBe('accepted');

    expect($base->fresh()->is_current)->toBeFalse();
    $new = ChapterVersion::find($response->json('new.id'));
    expect($new->is_current)->toBeTrue();
    expect($new->content)->toBe($merged);

    expect($chapter->scenes()->first()->content)->toBe($merged);
});

test('refine works on a non-pending base version', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $base = ChapterVersion::factory()->for($chapter)->create([
        'is_current' => true,
        'status' => VersionStatus::Accepted,
    ]);
    Scene::factory()->for($chapter)->create(['content' => '<p>Anything.</p>']);

    $this->postJson(
        route('chapters.ai.continueWriting.refine', [$book, $chapter, $base]),
        ['content' => '<p>Refined.</p>'],
    )->assertOk();
});

test('refine rejects a non-current base version', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $stale = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 1,
        'is_current' => false,
        'status' => VersionStatus::Accepted,
    ]);
    ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 2,
        'is_current' => true,
        'status' => VersionStatus::Accepted,
    ]);

    $this->postJson(
        route('chapters.ai.continueWriting.refine', [$book, $chapter, $stale]),
        ['content' => '<p>Refined.</p>'],
    )->assertStatus(409);
});

test('refine 404s when version does not belong to the chapter', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $otherChapter = Chapter::factory()->for($book)->for($storyline)->create();
    $foreignVersion = ChapterVersion::factory()->for($otherChapter)->create();

    $this->postJson(
        route('chapters.ai.continueWriting.refine', [$book, $chapter, $foreignVersion]),
        ['content' => '<p>Refined.</p>'],
    )->assertNotFound();
});

test('refine validates content is required', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $base = ChapterVersion::factory()->for($chapter)->create(['is_current' => true]);

    $this->postJson(
        route('chapters.ai.continueWriting.refine', [$book, $chapter, $base]),
        [],
    )->assertStatus(422);
});

test('agent instructions include preceding chapter summary when available', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 1,
        'title' => 'Arrival',
        'summary' => 'Elena arrives in Ravenholm.',
    ]);

    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 2,
    ]);
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $agent = new ContinueWritingAgent($book, $chapter, null, 120);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Preceding Chapter')
        ->toContain('Arrival')
        ->toContain('Elena arrives in Ravenholm.');
});
