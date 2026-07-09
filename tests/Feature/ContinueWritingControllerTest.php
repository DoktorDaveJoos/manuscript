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
        ['hint' => '', 'word_goal' => 60, 'expected_current_version_id' => null],
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
        ['hint' => 'Make it tense, focus on Anna', 'word_goal' => 80, 'expected_current_version_id' => null],
    )->assertOk();

    $agent = new ContinueWritingAgent($book, $chapter, 'Make it tense, focus on Anna', 80);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('AUTHOR DIRECTIVE')
        ->toContain('Make it tense, focus on Anna')
        ->toContain('approximately 80 words')
        ->toContain('MUST cover');
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
        ['hint' => str_repeat('a', 2001)],
    )->assertStatus(422);
});

test('continue writing accepts a hint up to 2000 characters', function () {
    ContinueWritingAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $this->post(
        route('chapters.ai.continueWriting', [$book, $chapter]),
        ['hint' => str_repeat('a', 2000), 'word_goal' => 60, 'expected_current_version_id' => null],
    )->assertOk();
});

test('continue writing rejects an invalid chapter link', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(
        route('chapters.ai.continueWriting', [$book, $chapter]),
        ['chapter_link' => 'bogus'],
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

test('continue writing rejects a stale expected_current_version_id with 409', function () {
    ContinueWritingAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);
    $current = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 2,
        'is_current' => true,
        'status' => VersionStatus::Accepted,
    ]);

    $this->postJson(
        route('chapters.ai.continueWriting', [$book, $chapter]),
        ['expected_current_version_id' => $current->id + 9999],
    )->assertStatus(409);
});

test('continue writing commit rejects a stale expected_current_version_id with 409', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>x</p>']);
    $current = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 2,
        'is_current' => true,
        'status' => VersionStatus::Accepted,
    ]);

    $this->postJson(
        route('chapters.ai.continueWriting.commit', [$book, $chapter]),
        ['expected_current_version_id' => $current->id + 9999],
    )->assertStatus(409);
});

test('continue writing stream requires expected_current_version_id', function () {
    ContinueWritingAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $this->postJson(
        route('chapters.ai.continueWriting', [$book, $chapter]),
        ['hint' => '', 'word_goal' => 60],
    )->assertStatus(422)
        ->assertJsonValidationErrors('expected_current_version_id');
});

test('continue writing commit requires expected_current_version_id', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>x</p>']);

    $this->postJson(
        route('chapters.ai.continueWriting.commit', [$book, $chapter]),
        [],
    )->assertStatus(422)
        ->assertJsonValidationErrors('expected_current_version_id');
});

test('continue writing commit rejects a null expectation when a current version exists', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>x</p>']);
    ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 2,
        'is_current' => true,
        'status' => VersionStatus::Accepted,
    ]);

    $this->postJson(
        route('chapters.ai.continueWriting.commit', [$book, $chapter]),
        ['expected_current_version_id' => null],
    )->assertStatus(409);
});

test('continue writing 404s when chapter does not belong to the book', function () {
    ContinueWritingAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $otherBook = Book::factory()->create();
    $storyline = Storyline::factory()->for($otherBook)->create();
    $chapter = Chapter::factory()->for($otherBook)->for($storyline)->create();

    $this->post(
        route('chapters.ai.continueWriting', [$book, $chapter]),
        ['expected_current_version_id' => null],
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
        ['expected_current_version_id' => $previous->id],
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
        ['expected_current_version_id' => null],
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

test('agent instructions render before/after split when supplied (inline mode)', function () {
    $book = Book::factory()->withAi()->create(['title' => 'Test', 'author' => 'A.', 'language' => 'English']);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'title' => 'Confrontation',
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>She walked to the window. The street was empty.</p>',
        'sort_order' => 0,
    ]);

    $agent = new ContinueWritingAgent(
        book: $book,
        chapter: $chapter,
        hint: null,
        wordGoal: 120,
        beforeProse: 'She walked to the window.',
        afterProse: 'The street was empty.',
    );

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Prose Before Cursor')
        ->toContain('She walked to the window.')
        ->toContain('Prose After Cursor')
        ->toContain('The street was empty.')
        ->toContain('mid-sentence')
        ->toContain('INSERTING prose')
        ->toContain('Bridge the gap')
        ->toContain('natural next sentence')
        ->toContain('Hand off, do not echo')
        ->not->toContain('off-limits')
        ->not->toContain('closed window')
        ->not->toContain('where the chapter prose ends');
});

test('agent instructions flag a truncated after excerpt', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create([
        'content' => '<p>She walked to the window. The street was empty.</p>',
    ]);

    $truncated = new ContinueWritingAgent(
        book: $book,
        chapter: $chapter,
        hint: null,
        wordGoal: 120,
        beforeProse: 'She walked to the window.',
        afterProse: 'The street was empty.',
        afterTruncated: true,
    );

    expect((string) $truncated->instructions())
        ->toContain('truncated')
        ->toContain('the chapter draft continues beyond it');

    $complete = new ContinueWritingAgent(
        book: $book,
        chapter: $chapter,
        hint: null,
        wordGoal: 120,
        beforeProse: 'She walked to the window.',
        afterProse: 'The street was empty.',
        afterTruncated: false,
    );

    expect((string) $complete->instructions())->not->toContain('truncated');
});

test('agent user message adapts to insertion mode and directive', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $append = new ContinueWritingAgent($book, $chapter, null, 120);
    expect($append->userMessage())
        ->toBe('Continue writing the chapter from where the prose ends.');

    $appendWithHint = new ContinueWritingAgent($book, $chapter, 'More tension', 120);
    expect($appendWithHint->userMessage())
        ->toBe('Continue writing the chapter from where the prose ends. Follow the AUTHOR DIRECTIVE.');

    $inline = new ContinueWritingAgent(
        book: $book,
        chapter: $chapter,
        hint: null,
        wordGoal: 120,
        beforeProse: 'Before.',
        afterProse: 'After.',
    );
    expect($inline->userMessage())
        ->toBe('Insert the continuation at the cursor, between the prose before and after it.');

    $inlineWithHint = new ContinueWritingAgent(
        book: $book,
        chapter: $chapter,
        hint: 'More tension',
        wordGoal: 120,
        beforeProse: 'Before.',
        afterProse: 'After.',
    );
    expect($inlineWithHint->userMessage())
        ->toBe('Insert the continuation at the cursor, between the prose before and after it. Follow the AUTHOR DIRECTIVE.');
});

test('controller streams with the mode-aware user message', function () {
    ContinueWritingAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create([
        'content' => '<p>She walked to the window. The street was empty.</p>',
    ]);

    $this->post(
        route('chapters.ai.continueWriting', [$book, $chapter]),
        [
            'before' => 'She walked to the window.',
            'after' => 'The street was empty.',
            'hint' => 'Make it tense',
            'word_goal' => 80,
            'expected_current_version_id' => null,
        ],
    )->assertOk();

    ContinueWritingAgent::assertPrompted(
        fn ($prompt) => $prompt->contains('Insert the continuation at the cursor')
            && $prompt->contains('Follow the AUTHOR DIRECTIVE.'),
    );
});

test('agent instructions stay in append mode when after is empty', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'title' => 'Opening',
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>The room was quiet.</p>',
        'sort_order' => 0,
    ]);

    $agent = new ContinueWritingAgent(
        book: $book,
        chapter: $chapter,
        hint: null,
        wordGoal: 120,
        beforeProse: 'The room was quiet.',
        afterProse: '',
    );

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Prose Before Cursor')
        ->toContain('The room was quiet.')
        ->toContain('where the chapter prose ends')
        ->not->toContain('Prose After Cursor')
        ->not->toContain('mid-sentence');
});

test('controller forwards before/after to the agent', function () {
    ContinueWritingAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create([
        'content' => '<p>She walked to the window. The street was empty.</p>',
    ]);

    $this->post(
        route('chapters.ai.continueWriting', [$book, $chapter]),
        [
            'before' => 'She walked to the window.',
            'after' => 'The street was empty.',
            'word_goal' => 80,
            'expected_current_version_id' => null,
        ],
    )->assertOk();

    ContinueWritingAgent::assertPrompted(function ($prompt) {
        $instructions = (string) $prompt->agent->instructions();

        return str_contains($instructions, 'Prose Before Cursor')
            && str_contains($instructions, 'She walked to the window.')
            && str_contains($instructions, 'Prose After Cursor')
            && str_contains($instructions, 'The street was empty.');
    });
});

test('agent instructions include up to three preceding chapters in order', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    foreach ([
        1 => ['title' => 'Old History', 'summary' => 'Should not appear — too far back.'],
        2 => ['title' => 'Arrival', 'summary' => 'Elena arrives in Ravenholm.'],
        3 => ['title' => 'The Letter', 'summary' => 'Elena receives a letter.'],
        4 => ['title' => 'The Stranger', 'summary' => 'A stranger appears at the inn.'],
    ] as $order => $data) {
        Chapter::factory()->for($book)->for($storyline)->create([
            'reader_order' => $order,
            'title' => $data['title'],
            'summary' => $data['summary'],
        ]);
    }

    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 5,
    ]);
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $agent = new ContinueWritingAgent($book, $chapter, null, 120);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Elena arrives in Ravenholm.')
        ->toContain('Elena receives a letter.')
        ->toContain('A stranger appears at the inn.')
        ->not->toContain('Should not appear — too far back.');

    $arrival = strpos($instructions, 'Elena arrives in Ravenholm.');
    $letter = strpos($instructions, 'Elena receives a letter.');
    $stranger = strpos($instructions, 'A stranger appears at the inn.');

    expect($arrival)->toBeLessThan($letter);
    expect($letter)->toBeLessThan($stranger);
});

test('agent instructions fall back to prose tail per preceding chapter when no summary', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();

    $ch1 = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 1,
        'title' => 'Opening',
        'summary' => null,
    ]);
    Scene::factory()->for($ch1)->create([
        'content' => '<p>The signal-flag '.str_repeat('older-word ', 500).'final-tail-one.</p>',
    ]);

    $ch2 = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 2,
        'title' => 'Middle',
        'summary' => null,
    ]);
    Scene::factory()->for($ch2)->create([
        'content' => '<p>The harbor '.str_repeat('mid-word ', 500).'final-tail-two.</p>',
    ]);

    $chapter = Chapter::factory()->for($book)->for($storyline)->create([
        'reader_order' => 3,
    ]);
    Scene::factory()->for($chapter)->create(['content' => '<p>Now.</p>']);

    $agent = new ContinueWritingAgent($book, $chapter, null, 120);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Ch1 — Opening')
        ->toContain('last excerpt')
        ->toContain('final-tail-one')
        ->toContain('Ch2 — Middle')
        ->toContain('final-tail-two')
        ->not->toContain('The signal-flag');
});

test('preceding chapters carry a continuity-background role and storyline labels', function () {
    $book = Book::factory()->withAi()->create();
    $mainArc = Storyline::factory()->for($book)->create(['name' => 'Main arc']);
    $sideArc = Storyline::factory()->for($book)->create(['name' => 'Side arc']);

    Chapter::factory()->for($book)->for($sideArc)->create([
        'reader_order' => 1,
        'title' => 'Elsewhere',
        'summary' => 'Meanwhile, in the side plot.',
    ]);

    $chapter = Chapter::factory()->for($book)->for($mainArc)->create([
        'reader_order' => 2,
    ]);
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $agent = new ContinueWritingAgent($book, $chapter, null, 120);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Continuity background only')
        ->toContain('stands on its own')
        ->toContain('Storyline: Side arc');
});

test('fresh chapter link forces a fresh opening on an empty chapter', function () {
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

    $agent = new ContinueWritingAgent(
        book: $book,
        chapter: $chapter,
        hint: null,
        wordGoal: 120,
        chapterLink: 'fresh',
    );

    expect((string) $agent->instructions())
        ->toContain('FRESH opening')
        ->toContain('do not continue the preceding chapter')
        ->not->toContain('pick up DIRECTLY');
});

test('continue chapter link asks for direct continuation on an empty chapter', function () {
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

    $agent = new ContinueWritingAgent(
        book: $book,
        chapter: $chapter,
        hint: null,
        wordGoal: 120,
        chapterLink: 'continue',
    );

    expect((string) $agent->instructions())
        ->toContain('pick up DIRECTLY')
        ->not->toContain('FRESH opening');
});

test('chapter link is ignored once prose exists before the cursor', function () {
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
    Scene::factory()->for($chapter)->create(['content' => '<p>Mid-chapter prose.</p>']);

    $agent = new ContinueWritingAgent(
        book: $book,
        chapter: $chapter,
        hint: null,
        wordGoal: 120,
        beforeProse: 'Mid-chapter prose.',
        afterProse: '',
        chapterLink: 'fresh',
    );

    expect((string) $agent->instructions())
        ->toContain('Continuity background only')
        ->not->toContain('FRESH opening');
});

test('controller forwards the chapter link to the agent', function () {
    ContinueWritingAgent::fake(['ok']);

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

    $this->post(
        route('chapters.ai.continueWriting', [$book, $chapter]),
        ['chapter_link' => 'fresh', 'word_goal' => 60, 'expected_current_version_id' => null],
    )->assertOk();

    ContinueWritingAgent::assertPrompted(
        fn ($prompt) => str_contains((string) $prompt->agent->instructions(), 'FRESH opening'),
    );
});

test('beats header defers to the author directive when a hint is given', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $plotPoint = PlotPoint::factory()->for($book)->create();
    $beat = Beat::factory()->for($plotPoint)->create(['title' => 'A beat']);
    $chapter->beats()->attach($beat, ['sort_order' => 0]);

    $withHint = new ContinueWritingAgent($book, $chapter, 'Focus on the rain', 120);
    expect((string) $withHint->instructions())
        ->toContain('background reference — the author directive leads')
        ->not->toContain('advance these in order');

    $withoutHint = new ContinueWritingAgent($book, $chapter, null, 120);
    expect((string) $withoutHint->instructions())
        ->toContain('advance these in order');
});

test('append mode warns when another scene follows in the chapter', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Scene one prose.</p>']);

    $withFollowingScene = new ContinueWritingAgent(
        book: $book,
        chapter: $chapter,
        hint: null,
        wordGoal: 120,
        beforeProse: 'Scene one prose.',
        afterProse: '',
        sceneFollows: true,
    );

    expect((string) $withFollowingScene->instructions())
        ->toContain('Another scene follows later in this chapter')
        ->toContain('do not wrap up the chapter');

    $lastScene = new ContinueWritingAgent(
        book: $book,
        chapter: $chapter,
        hint: null,
        wordGoal: 120,
        beforeProse: 'Scene one prose.',
        afterProse: '',
        sceneFollows: false,
    );

    expect((string) $lastScene->instructions())
        ->not->toContain('Another scene follows');
});

test('controller forwards scene follows and after truncated flags', function () {
    ContinueWritingAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create([
        'content' => '<p>She walked to the window. The street was empty.</p>',
    ]);

    $this->post(
        route('chapters.ai.continueWriting', [$book, $chapter]),
        [
            'before' => 'She walked to the window.',
            'after' => 'The street was empty.',
            'after_truncated' => true,
            'scene_follows' => true,
            'word_goal' => 60,
            'expected_current_version_id' => null,
        ],
    )->assertOk();

    ContinueWritingAgent::assertPrompted(function ($prompt) {
        $instructions = (string) $prompt->agent->instructions();

        return str_contains($instructions, 'the chapter draft continues beyond it');
    });
});
