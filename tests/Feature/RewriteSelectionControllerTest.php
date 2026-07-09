<?php

use App\Ai\Agents\RewriteSelectionAgent;
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

test('rewrite selection streams the rewrite as SSE', function () {
    RewriteSelectionAgent::fake(['She watched the rain.']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create([
        'content' => '<p>She looked outside.</p>',
        'sort_order' => 0,
    ]);

    $response = $this->post(
        route('chapters.ai.rewriteSelection', [$book, $chapter]),
        [
            'selection' => 'She looked outside.',
            'hint' => '',
            'before' => '',
            'after' => '',
        ],
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

    expect($combined)->toBe('She watched the rain.');

    RewriteSelectionAgent::assertPrompted(fn ($prompt) => true);
});

test('rewrite selection forwards the hint and surrounding prose to the agent', function () {
    RewriteSelectionAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $this->post(
        route('chapters.ai.rewriteSelection', [$book, $chapter]),
        [
            'selection' => 'She looked outside.',
            'hint' => 'Make it more tense.',
            'before' => 'The room was quiet.',
            'after' => 'A knock at the door.',
        ],
    )->assertOk();

    $agent = new RewriteSelectionAgent(
        book: $book,
        chapter: $chapter,
        selection: 'She looked outside.',
        hint: 'Make it more tense.',
        beforeProse: 'The room was quiet.',
        afterProse: 'A knock at the door.',
    );

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('AUTHOR DIRECTIVE')
        ->toContain('Make it more tense.')
        ->toContain('SELECTION (rewrite this)')
        ->toContain('She looked outside.')
        ->toContain('Prose Before Selection')
        ->toContain('The room was quiet.')
        ->toContain('Prose After Selection')
        ->toContain('A knock at the door.');
});

test('rewrite selection rejects an over-long hint', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(
        route('chapters.ai.rewriteSelection', [$book, $chapter]),
        [
            'selection' => 'Some text.',
            'hint' => str_repeat('a', 2001),
        ],
    )->assertStatus(422);
});

test('rewrite selection accepts a hint up to 2000 characters', function () {
    RewriteSelectionAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $this->post(
        route('chapters.ai.rewriteSelection', [$book, $chapter]),
        [
            'selection' => 'Some text.',
            'hint' => str_repeat('a', 2000),
        ],
    )->assertOk();
});

test('rewrite selection requires a selection', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(
        route('chapters.ai.rewriteSelection', [$book, $chapter]),
        [],
    )->assertStatus(422);

    $this->postJson(
        route('chapters.ai.rewriteSelection', [$book, $chapter]),
        ['selection' => ''],
    )->assertStatus(422);
});

test('rewrite selection fails when no AI provider is configured', function () {
    $book = Book::factory()->create();
    AiSetting::factory()->withoutKey()->create([
        'provider' => AiProvider::Anthropic,
        'enabled' => true,
    ]);
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();

    $this->postJson(
        route('chapters.ai.rewriteSelection', [$book, $chapter]),
        ['selection' => 'Some text.'],
    )->assertStatus(422);
});

test('rewrite selection 404s when chapter does not belong to the book', function () {
    RewriteSelectionAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $otherBook = Book::factory()->create();
    $storyline = Storyline::factory()->for($otherBook)->create();
    $chapter = Chapter::factory()->for($otherBook)->for($storyline)->create();

    $this->post(
        route('chapters.ai.rewriteSelection', [$book, $chapter]),
        ['selection' => 'Some text.'],
    )->assertNotFound();
});

test('agent instructions include beats, characters, wiki entries, and selection', function () {
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

    $agent = new RewriteSelectionAgent(
        book: $book,
        chapter: $chapter,
        selection: 'Anna paused at the threshold.',
    );
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Anna confronts her mother')
        ->toContain('inheritance')
        ->toContain('Anna')
        ->toContain('POV')
        ->toContain('The library')
        ->toContain('Locations')
        ->toContain('Confrontation')
        ->toContain('Anna paused at the threshold.')
        ->toContain('rewrite this');
});

test('agent instructions include a preceding chapter summary when available', function () {
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

    $agent = new RewriteSelectionAgent(
        book: $book,
        chapter: $chapter,
        selection: 'She paused.',
    );
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Preceding Chapter')
        ->toContain('Arrival')
        ->toContain('Elena arrives in Ravenholm.');
});

test('commit snapshots scenes into a new accepted rewrite_selection version', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    $previous = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 3,
        'content' => '<p>Old prose.</p>',
        'is_current' => true,
        'status' => VersionStatus::Accepted,
        'source' => VersionSource::Original,
    ]);
    Scene::factory()->for($chapter)->create([
        'content' => '<p>New prose.</p>',
        'sort_order' => 0,
    ]);

    $response = $this->postJson(
        route('chapters.ai.rewriteSelection.commit', [$book, $chapter]),
    )->assertOk();

    $payload = $response->json();
    expect($payload['previous']['id'])->toBe($previous->id);
    expect($payload['previous']['version_number'])->toBe(3);
    expect($payload['new']['version_number'])->toBe(4);
    expect($payload['new']['source'])->toBe('rewrite_selection');
    expect($payload['new']['status'])->toBe('accepted');

    expect($previous->fresh()->is_current)->toBeFalse();
    $new = ChapterVersion::find($payload['new']['id']);
    expect($new->is_current)->toBeTrue();
    expect($new->content)->toContain('New prose.');
});

test('commit creates the first version when no current version exists', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create([
        'content' => '<p>Fresh rewrite.</p>',
        'sort_order' => 0,
    ]);

    $response = $this->postJson(
        route('chapters.ai.rewriteSelection.commit', [$book, $chapter]),
    )->assertOk();

    expect($response->json('previous'))->toBeNull();
    expect($response->json('new.version_number'))->toBe(1);
    expect($response->json('new.source'))->toBe('rewrite_selection');
});

test('commit 404s when chapter does not belong to the book', function () {
    $book = Book::factory()->withAi()->create();
    $otherBook = Book::factory()->create();
    $storyline = Storyline::factory()->for($otherBook)->create();
    $chapter = Chapter::factory()->for($otherBook)->for($storyline)->create();

    $this->postJson(
        route('chapters.ai.rewriteSelection.commit', [$book, $chapter]),
    )->assertNotFound();
});

test('stream rejects a stale expected_current_version_id with 409', function () {
    RewriteSelectionAgent::fake(['ok']);

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
        route('chapters.ai.rewriteSelection', [$book, $chapter]),
        [
            'selection' => 'Some text.',
            'expected_current_version_id' => $current->id + 9999,
        ],
    )->assertStatus(409);
});

test('stream passes when expected_current_version_id matches', function () {
    RewriteSelectionAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);
    $current = ChapterVersion::factory()->for($chapter)->create([
        'version_number' => 2,
        'is_current' => true,
        'status' => VersionStatus::Accepted,
    ]);

    $this->post(
        route('chapters.ai.rewriteSelection', [$book, $chapter]),
        [
            'selection' => 'Some text.',
            'expected_current_version_id' => $current->id,
        ],
    )->assertOk();
});

test('commit rejects a stale expected_current_version_id with 409', function () {
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
        route('chapters.ai.rewriteSelection.commit', [$book, $chapter]),
        ['expected_current_version_id' => $current->id + 9999],
    )->assertStatus(409);
});

test('stream requires expected_current_version_id', function () {
    RewriteSelectionAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $this->postJson(
        route('chapters.ai.rewriteSelection', [$book, $chapter]),
        ['selection' => 'Some text.'],
    )->assertStatus(422)
        ->assertJsonValidationErrors('expected_current_version_id');
});

test('commit requires expected_current_version_id', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>x</p>']);

    $this->postJson(
        route('chapters.ai.rewriteSelection.commit', [$book, $chapter]),
        [],
    )->assertStatus(422)
        ->assertJsonValidationErrors('expected_current_version_id');
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

    $agent = new RewriteSelectionAgent(
        book: $book,
        chapter: $chapter,
        selection: 'Some prose.',
    );
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Preceding Chapters')
        ->toContain('Continuity background only')
        ->toContain('do not import their events')
        ->toContain('Storyline: Side arc');
});

test('beats header defers to the author directive when a hint is given', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $plotPoint = PlotPoint::factory()->for($book)->create();
    $beat = Beat::factory()->for($plotPoint)->create(['title' => 'A beat']);
    $chapter->beats()->attach($beat, ['sort_order' => 0]);

    $withHint = new RewriteSelectionAgent(
        book: $book,
        chapter: $chapter,
        selection: 'Some prose.',
        hint: 'Focus on the rain',
    );
    expect((string) $withHint->instructions())
        ->toContain('background reference — the author directive leads');

    $withoutHint = new RewriteSelectionAgent(
        book: $book,
        chapter: $chapter,
        selection: 'Some prose.',
    );
    expect((string) $withoutHint->instructions())
        ->toContain('do not advance beyond the selection')
        ->not->toContain('author directive leads');
});

test('agent instructions flag truncated surrounding excerpts', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create([
        'content' => '<p>Earlier prose. The middle part. Later prose.</p>',
    ]);

    $truncated = new RewriteSelectionAgent(
        book: $book,
        chapter: $chapter,
        selection: 'The middle part.',
        hint: null,
        beforeProse: 'Earlier prose.',
        afterProse: 'Later prose.',
        beforeTruncated: true,
        afterTruncated: true,
    );

    expect((string) $truncated->instructions())
        ->toContain('the chapter begins before it')
        ->toContain('the chapter draft continues beyond it');

    $complete = new RewriteSelectionAgent(
        book: $book,
        chapter: $chapter,
        selection: 'The middle part.',
        hint: null,
        beforeProse: 'Earlier prose.',
        afterProse: 'Later prose.',
        beforeTruncated: false,
        afterTruncated: false,
    );

    expect((string) $complete->instructions())->not->toContain('truncated');
});

test('agent flags surrounds capped by the server-side word limit', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $longProse = implode(' ', array_fill(0, 250, 'word'));

    $agent = new RewriteSelectionAgent(
        book: $book,
        chapter: $chapter,
        selection: 'The middle part.',
        hint: null,
        beforeProse: $longProse,
        afterProse: $longProse,
    );

    expect((string) $agent->instructions())
        ->toContain('the chapter begins before it')
        ->toContain('the chapter draft continues beyond it');
});

test('agent user message points at the author directive when a hint is given', function () {
    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $plain = new RewriteSelectionAgent(
        book: $book,
        chapter: $chapter,
        selection: 'Some text.',
    );
    expect($plain->userMessage())
        ->toBe('Rewrite the SELECTION so it replaces the original passage and reads seamlessly with the surrounding prose.');

    $withHint = new RewriteSelectionAgent(
        book: $book,
        chapter: $chapter,
        selection: 'Some text.',
        hint: 'Tighter',
    );
    expect($withHint->userMessage())
        ->toBe('Rewrite the SELECTION so it replaces the original passage and reads seamlessly with the surrounding prose. Follow the AUTHOR DIRECTIVE.');
});

test('controller streams with the directive-aware user message', function () {
    RewriteSelectionAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $this->post(
        route('chapters.ai.rewriteSelection', [$book, $chapter]),
        [
            'selection' => 'Some text.',
            'hint' => 'Make it tense',
        ],
    )->assertOk();

    RewriteSelectionAgent::assertPrompted(
        fn ($prompt) => $prompt->contains('Rewrite the SELECTION')
            && $prompt->contains('Follow the AUTHOR DIRECTIVE.'),
    );
});

test('controller forwards truncation flags to the agent', function () {
    RewriteSelectionAgent::fake(['ok']);

    $book = Book::factory()->withAi()->create();
    $storyline = Storyline::factory()->for($book)->create();
    $chapter = Chapter::factory()->for($book)->for($storyline)->create();
    Scene::factory()->for($chapter)->create(['content' => '<p>Some prose.</p>']);

    $this->post(
        route('chapters.ai.rewriteSelection', [$book, $chapter]),
        [
            'selection' => 'Some text.',
            'before' => 'Earlier.',
            'after' => 'Later.',
            'before_truncated' => true,
            'after_truncated' => true,
        ],
    )->assertOk();

    RewriteSelectionAgent::assertPrompted(
        fn ($prompt) => str_contains((string) $prompt->agent->instructions(), 'the chapter begins before it')
            && str_contains((string) $prompt->agent->instructions(), 'the chapter draft continues beyond it'),
    );
});
