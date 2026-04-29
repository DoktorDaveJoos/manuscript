<?php

use App\Ai\Agents\PlotCoachAgent;
use App\Ai\Tools\LookupExistingEntities;
use App\Ai\Tools\Plot\ApplyPlotCoachBatch;
use App\Ai\Tools\Plot\GetEntityDetails;
use App\Ai\Tools\Plot\GetPlotBoardState;
use App\Ai\Tools\Plot\ProposeBatch;
use App\Ai\Tools\Plot\ProposeChapterPlan;
use App\Ai\Tools\Plot\UndoLastBatch;
use App\Ai\Tools\RetrieveManuscriptContext;
use App\Enums\Genre;
use App\Enums\PlotCoachSessionStatus;
use App\Enums\PlotCoachStage;
use App\Enums\WikiEntryKind;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\PlotCoachBatch;
use App\Models\PlotCoachProposal;
use App\Models\PlotCoachSession;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Models\WikiEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\Enums\Lab;

test('plot coach agent registers GetPlotBoardState and related tools', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $agent = new PlotCoachAgent($book, $session);
    $tools = iterator_to_array($agent->tools());

    expect($tools)->toHaveCount(8);

    $toolClasses = array_map(fn ($t) => $t::class, $tools);

    expect($toolClasses)->toContain(GetPlotBoardState::class);
    expect($toolClasses)->toContain(GetEntityDetails::class);
    expect($toolClasses)->toContain(RetrieveManuscriptContext::class);
    expect($toolClasses)->toContain(LookupExistingEntities::class);
});

test('plot coach agent registers the batch tools', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $agent = new PlotCoachAgent($book, $session);
    $toolClasses = array_map(fn ($t) => $t::class, iterator_to_array($agent->tools()));

    expect($toolClasses)->toContain(ProposeBatch::class);
    expect($toolClasses)->toContain(ProposeChapterPlan::class);
    expect($toolClasses)->toContain(ApplyPlotCoachBatch::class);
    expect($toolClasses)->toContain(UndoLastBatch::class);
});

test('plot coach agent emits refinement-stage chapter-handoff guidance', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'stage' => PlotCoachStage::Refinement,
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Current stage: Refinement')
        ->toContain('ProposeChapterPlan')
        ->toContain('additive');
});

test('plot coach agent does not nudge the author to archive at mid-session length', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'stage' => PlotCoachStage::Plotting,
        'user_turn_count' => 150,
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->not->toContain('Session length')
        ->not->toContain('archiving')
        ->not->toContain('start fresh');
});

test('plot coach agent offers a handoff summary only at the extreme-length threshold', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'stage' => PlotCoachStage::Plotting,
        'user_turn_count' => 260,
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('250+ user turns');
    expect($instructions)->toContain('handoff summary');
});

test('plot coach agent emits plotting guidance when session is in Plotting stage', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'stage' => PlotCoachStage::Plotting,
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Current stage: Plotting')
        ->toContain('Batch discipline');
});

test('plot coach agent composes intake-stage instructions with session state', function () {
    $book = Book::factory()->create([
        'title' => 'The Copper Hour',
        'genre' => Genre::Thriller,
    ]);

    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'stage' => PlotCoachStage::Intake,
        'decisions' => ['genre' => 'thriller'],
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Intake')
        ->toContain('The Copper Hour')
        ->toContain('thriller')
        ->toContain('## Bible')
        ->toContain('## Session counters');
});

test('plot coach instructions place volatile counters AFTER the cache breakpoints', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'stage' => PlotCoachStage::Plotting,
        'user_turn_count' => 17,
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    $staticAt = strpos($instructions, PlotCoachAgent::CACHE_BREAKPOINT_STATIC);
    $bibleAt = strpos($instructions, PlotCoachAgent::CACHE_BREAKPOINT_BIBLE);
    $countersAt = strpos($instructions, '## Session counters');

    expect($staticAt)->not->toBeFalse();
    expect($bibleAt)->not->toBeFalse();
    expect($countersAt)->not->toBeFalse();

    // Order must be: persona/stage/handoff → STATIC marker → bible → BIBLE marker → counters
    expect($staticAt)->toBeLessThan($bibleAt);
    expect($bibleAt)->toBeLessThan($countersAt);

    // Volatile counters MUST be after both breakpoints — otherwise the cache prefix breaks every turn.
    expect(substr_count($instructions, '"user_turn_count": 17'))->toBe(1);
    expect(strpos($instructions, '"user_turn_count": 17'))->toBeGreaterThan($bibleAt);
});

test('plot coach isTrivialTurn classifies system-prefixed acks, wire signals, and short approvals/cancels/undos', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $agent = new PlotCoachAgent($book, $session);

    // System-applied approval / cancel / undo notes — should always route cheap.
    expect($agent->isTrivialTurn('[system: The batch was applied. ...]'))->toBeTrue();
    expect($agent->isTrivialTurn('[system: Nothing to undo. ...]'))->toBeTrue();

    // Bare wire signals (defensive).
    expect($agent->isTrivialTurn('APPROVE:batch:'.Str::uuid()))->toBeTrue();
    expect($agent->isTrivialTurn('UNDO:last'))->toBeTrue();
    expect($agent->isTrivialTurn('CANCEL:batch:'.Str::uuid()))->toBeTrue();

    // Short free-text approvals — EN / DE / ES.
    foreach (['yes', 'go ahead', 'save it', 'OK', 'ja', 'passt', 'speichern', 'sí', 'dale'] as $msg) {
        expect($agent->isTrivialTurn($msg))->toBeTrue("'{$msg}' should be trivial");
    }

    // Short rejections / undos.
    foreach (['no', 'cancel', 'undo', 'nein', 'abbrechen', 'rückgängig'] as $msg) {
        expect($agent->isTrivialTurn($msg))->toBeTrue("'{$msg}' should be trivial");
    }

    // Real coaching turns — must remain on the smart model.
    foreach ([
        'I am stuck on the second act. Help.',
        'Was meinst du mit "der unverlässliche Erzähler"?',
        'Hmm, the wound feels too literal. Maybe shift it to a betrayal of someone he trusted blindly?',
        'yes, but only if the storyline is the romance one — otherwise no',
    ] as $msg) {
        expect($agent->isTrivialTurn($msg))->toBeFalse("'{$msg}' should NOT be trivial");
    }

    // Boundary: an "approval-shaped" message that exceeds the 30-char ceiling
    // is a real coaching turn (the user qualified the approval).
    expect($agent->isTrivialTurn('yes please save this for me right now'))->toBeFalse();
});

test('plot coach modelForTurn returns the active providers cheapest model for trivial turns', function () {
    config(['ai.default' => 'anthropic']);
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();
    $agent = new PlotCoachAgent($book, $session);

    $cheap = $agent->modelForTurn('yes');
    $smart = $agent->modelForTurn('I want to rework the second act so that the betrayal lands sooner.');

    // The cheap path must resolve to whatever the SDK considers cheapest on
    // the active provider — tracking SDK defaults is the whole point.
    expect($cheap)->toBe(Ai::textProvider('anthropic')->cheapestTextModel());
    expect($smart)->toBeNull();
});

test('plot coach providerOptions emits Anthropic cache_control markers on the static prefix and bible blocks', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'stage' => PlotCoachStage::Plotting,
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $options = $agent->providerOptions(Lab::Anthropic);

    expect($options)->toHaveKey('system');

    $blocks = $options['system'];
    expect($blocks)->toHaveCount(3);

    // Block 1 — static persona/stage/handoff: cached.
    expect($blocks[0]['type'])->toBe('text');
    expect($blocks[0])->toHaveKey('cache_control');
    expect($blocks[0]['cache_control'])->toBe(['type' => 'ephemeral']);

    // Block 2 — bible: cached.
    expect($blocks[1]['type'])->toBe('text');
    expect($blocks[1])->toHaveKey('cache_control');
    expect($blocks[1]['cache_control'])->toBe(['type' => 'ephemeral']);

    // Block 3 — volatile counters: NOT cached.
    expect($blocks[2]['type'])->toBe('text');
    expect($blocks[2])->not->toHaveKey('cache_control');

    // Sentinel strings must not bleed into the rendered text.
    foreach ($blocks as $block) {
        expect($block['text'])->not->toContain(PlotCoachAgent::CACHE_BREAKPOINT_STATIC);
        expect($block['text'])->not->toContain(PlotCoachAgent::CACHE_BREAKPOINT_BIBLE);
    }
});

test('plot coach providerOptions returns empty for non-Anthropic providers (auto-caching handles them)', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $agent = new PlotCoachAgent($book, $session);

    expect($agent->providerOptions(Lab::OpenAI))->toBe([]);
    expect($agent->providerOptions(Lab::Gemini))->toBe([]);
    expect($agent->providerOptions('openrouter'))->toBe([]);
});

test('plot coach instructions remain byte-stable for cacheable prefix when only volatile fields change', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'stage' => PlotCoachStage::Plotting,
        'user_turn_count' => 5,
    ]);

    $agent1 = new PlotCoachAgent($book, $session);
    $beforeStatic1 = substr((string) $agent1->instructions(), 0, strpos((string) $agent1->instructions(), PlotCoachAgent::CACHE_BREAKPOINT_STATIC));

    // Bump only the turn counter — pure volatile change.
    $session->refresh();
    $session->update(['user_turn_count' => 6]);

    $agent2 = new PlotCoachAgent($book, $session->fresh());
    $beforeStatic2 = substr((string) $agent2->instructions(), 0, strpos((string) $agent2->instructions(), PlotCoachAgent::CACHE_BREAKPOINT_STATIC));

    expect($beforeStatic2)->toBe($beforeStatic1);
});

test('plot coach instructions are memoized within an agent instance', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $agent = new PlotCoachAgent($book, $session);

    // Cache is empty before any call.
    $cacheProperty = new ReflectionProperty($agent, 'cachedInstructions');
    expect($cacheProperty->getValue($agent))->toBeNull();

    $first = (string) $agent->instructions();

    // After the first call, the cache holds the rendered string.
    expect($cacheProperty->getValue($agent))->toBe($first);

    // A second call returns the cached value verbatim (catches a regression
    // where memoization gets dropped — string equality alone wouldn't).
    $second = (string) $agent->instructions();
    expect($second)->toBe($first);
    expect($cacheProperty->getValue($agent))->toBe($first);
});

test('plot coach agent returns empty stage-guidance for stages without dedicated prompts', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'stage' => PlotCoachStage::Structure,
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->not->toContain('We need to pin down')
        ->not->toContain('Batch discipline')
        ->toContain('editorial plot coach');
});

test('plot coach agent includes static persona in all stages', function () {
    foreach (PlotCoachStage::cases() as $stage) {
        $book = Book::factory()->create();
        $session = PlotCoachSession::factory()->for($book, 'book')->create([
            'stage' => $stage,
        ]);

        $agent = new PlotCoachAgent($book, $session);
        $instructions = (string) $agent->instructions();

        expect($instructions)->toContain('editorial plot coach');
        expect($instructions)->toContain('Voice rules');
        expect($instructions)->toContain('Discipline rules');
    }
});

test('plot coach agent surfaces book_id in every stage so tools receive the correct value', function () {
    foreach (PlotCoachStage::cases() as $stage) {
        $book = Book::factory()->create();
        $session = PlotCoachSession::factory()->for($book, 'book')->create([
            'stage' => $stage,
        ]);

        $agent = new PlotCoachAgent($book, $session);
        $instructions = (string) $agent->instructions();

        expect($instructions)->toContain("book_id: {$book->id}");
        expect($instructions)->toContain('"book_id": '.$book->id);
    }
});

test('plot coach agent includes a freshly-computed handoff from the parent session on every turn', function () {
    $book = Book::factory()->create();

    $conversationId = (string) Str::uuid();
    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => null,
        'title' => 'prior session',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $parent = PlotCoachSession::factory()->for($book, 'book')->create([
        'stage' => PlotCoachStage::Intake,
        'status' => PlotCoachSessionStatus::Archived,
        'agent_conversation_id' => $conversationId,
        'archive_summary' => 'stale summary that should be ignored',
    ]);

    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid(),
        'conversation_id' => $parent->agent_conversation_id,
        'agent' => PlotCoachAgent::class,
        'role' => 'user',
        'content' => 'Premise: Aliens find Earth via Voyager and send biomass probes.',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $child = PlotCoachSession::factory()->for($book, 'book')->create([
        'stage' => PlotCoachStage::Intake,
        'parent_session_id' => $parent->id,
    ]);

    $agent = new PlotCoachAgent($book, $child);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain("Handoff from previous session (#{$parent->id})")
        ->toContain('transcript_digest')
        ->toContain('Voyager')
        ->not->toContain('stale summary that should be ignored');
});

test('plot coach agent omits handoff block when the session has no parent', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'parent_session_id' => null,
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)->not->toContain('Handoff from previous session');
});

test('plot coach agent prompt instructs structured markdown for entity descriptions so wiki entries are scannable', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Description style')
        ->toContain('Markdown')
        ->toContain('headings')
        ->toContain('bullet lists');
});

test('plot coach agent state block surfaces saved entities so the AI knows what exists without tool calls', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    Character::factory()->for($book)->create(['name' => 'Maja']);
    Character::factory()->for($book)->create(['name' => 'John']);
    Storyline::factory()->for($book)->create(['name' => 'Main']);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('saved_entities')
        ->toContain('Maja')
        ->toContain('John')
        ->toContain('Main')
        ->toContain('"count": 2')
        ->toContain('"count": 1');
});

test('plot coach agent state block includes recent applied batches for continuity when older turns drop', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    PlotCoachBatch::create([
        'session_id' => $session->id,
        'summary' => 'Saved Maja as protagonist',
        'payload' => ['version' => 1, 'writes' => [['type' => 'character', 'id' => 1]]],
        'applied_at' => now(),
        'undo_window_expires_at' => now()->addHour(),
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('recent_batches')
        ->toContain('Saved Maja as protagonist');
});

test('plot coach agent state block lists pending proposals so duplicate proposals are avoided', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    PlotCoachProposal::create([
        'public_id' => (string) Str::uuid(),
        'session_id' => $session->id,
        'kind' => 'batch',
        'writes' => [['type' => 'character', 'data' => ['name' => 'Maja']]],
        'summary' => 'Save Maja',
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('pending_proposals')
        ->toContain('Save Maja');
});

test('plot coach agent renders wiki_entry kind as its string value so enum casts do not crash the prompt', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    WikiEntry::create([
        'book_id' => $book->id,
        'kind' => WikiEntryKind::Lore->value,
        'name' => 'Majas Herkunft',
        'ai_description' => 'Maja Deutsche ist und dies ihre plausible Schutzreaktion im Ausland prägt.',
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Majas Herkunft')
        ->toContain('kind=lore')
        ->toContain('Schutzreaktion');
});

test('plot coach agent surfaces entity descriptions so saved entities carry meaning, not just titles', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    Character::factory()->for($book)->create([
        'name' => 'Maja',
        'description' => 'A research chemist wracked by guilt after the lab accident.',
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Maja')
        ->toContain('research chemist')
        ->toContain('lab accident');
});

test('plot coach agent injects a rolling digest of older turns so long sessions keep continuity', function () {
    $book = Book::factory()->create();

    $conversationId = (string) Str::uuid();
    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => null,
        'title' => 'ongoing session',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'agent_conversation_id' => $conversationId,
        'user_turn_count' => 60,
    ]);

    // 60 user turns + 60 assistant = 120 messages. First 80 are "pre-tail"
    // (before the 40-message replay window) and should be digested.
    for ($i = 1; $i <= 60; $i++) {
        foreach (['user', 'assistant'] as $role) {
            DB::table('agent_conversation_messages')->insert([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversationId,
                'agent' => PlotCoachAgent::class,
                'role' => $role,
                'content' => $role === 'user' && $i === 1
                    ? 'Opening premise: a research chemist hides a lab accident.'
                    : "turn {$i} {$role} line",
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '[]',
                'meta' => '[]',
                'created_at' => now()->addSeconds($i * 2 + ($role === 'assistant' ? 1 : 0)),
                'updated_at' => now(),
            ]);
        }
    }

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Earlier in this conversation')
        ->toContain('Author:')
        ->toContain('Opening premise')
        ->toContain('research chemist hides a lab accident');

    $session->refresh();
    expect((int) $session->rolling_digest_through_turn)->toBe(60);
    expect((string) $session->rolling_digest)->not->toBe('');
});

test('plot coach agent skips the rolling digest when the session is short enough to replay verbatim', function () {
    $book = Book::factory()->create();

    $conversationId = (string) Str::uuid();
    DB::table('agent_conversations')->insert([
        'id' => $conversationId,
        'user_id' => null,
        'title' => 'short session',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'agent_conversation_id' => $conversationId,
        'user_turn_count' => 5,
    ]);

    for ($i = 1; $i <= 5; $i++) {
        foreach (['user', 'assistant'] as $role) {
            DB::table('agent_conversation_messages')->insert([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversationId,
                'agent' => PlotCoachAgent::class,
                'role' => $role,
                'content' => "turn {$i} {$role}",
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '[]',
                'meta' => '[]',
                'created_at' => now()->addSeconds($i * 2 + ($role === 'assistant' ? 1 : 0)),
                'updated_at' => now(),
            ]);
        }
    }

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)->not->toContain('Earlier in this conversation');
});

test('plot coach agent caps conversation window to 40 messages to resist long-session style drift', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $agent = new PlotCoachAgent($book, $session);

    $reflection = new ReflectionMethod($agent, 'maxConversationMessages');
    $reflection->setAccessible(true);

    expect($reflection->invoke($agent))->toBe(40);
});

test('plot coach agent surfaces full character descriptions so the coach stays consistent with prior writing', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    // ~330-char structured description — would have been truncated at 120 before.
    $description = '### Role'."\n".'Research chemist running the New England lab.'."\n\n"
        .'### Wants'."\n".'Control after the 2009 accident.'."\n\n"
        .'### Wound'."\n".'She let Hofmann take the blame for the controlled-error breach. He vanished. She still dreams of him.';

    Character::factory()->for($book)->create([
        'name' => 'Maja',
        'ai_description' => $description,
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Maja')
        ->toContain('Research chemist running the New England lab.')
        ->toContain('### Wound')
        ->toContain('Hofmann take the blame')
        ->toContain('still dreams of him');
});

test('plot coach agent surfaces full wiki entry descriptions for the story bible', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    // ~280-char lore entry — would have been truncated at 120 before.
    $description = '### What it is'."\n".'A communication channel that opens only under controlled error conditions.'."\n\n"
        .'### Why it matters'."\n".'The story turns on Maja\'s decision to trigger one — and the cost it carries.'."\n\n"
        .'### Limits'."\n".'Each opening narrows the next.';

    WikiEntry::create([
        'book_id' => $book->id,
        'kind' => WikiEntryKind::Lore->value,
        'name' => 'The interface phenomenon',
        'ai_description' => $description,
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('The interface phenomenon')
        ->toContain('controlled error conditions')
        ->toContain('Why it matters')
        ->toContain('Each opening narrows the next');
});

test('plot coach agent omits beat prose from the state block (lazy-loaded via GetEntityDetails)', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $point = PlotPoint::factory()->for($book)->create(['title' => 'Auslöser']);

    DB::table('beats')->insert([
        'plot_point_id' => $point->id,
        'title' => 'First cut',
        'description' => 'A long beat description that should never reach the prompt unless asked.',
        'sort_order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('First cut');
    expect($instructions)->not->toContain('A long beat description');
});

test('plot coach agent omits chapter summary from the state block (lazy-loaded via GetEntityDetails)', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $storyline = Storyline::factory()->for($book)->create();

    Chapter::factory()
        ->for($book)
        ->for($storyline)
        ->create([
            'title' => 'Chapter 1: Cold open',
            'summary' => 'Detailed prose summary of the cold open scene.',
        ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)->toContain('Chapter 1: Cold open');
    expect($instructions)->not->toContain('Detailed prose summary');
});

test('plot coach agent omits plot point prose from the state block (lazy-loaded via GetEntityDetails)', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    $longDescription = str_repeat('Maja boards the plane in heavy snow. ', 12);

    PlotPoint::factory()->for($book)->create([
        'title' => 'Pulled to Jakutsk',
        'description' => $longDescription,
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    // Title and id surface in state; the body is fully suppressed — the agent
    // must call GetEntityDetails when it needs the prose.
    expect($instructions)->toContain('Pulled to Jakutsk');
    expect($instructions)->not->toContain('Maja boards the plane in heavy snow.');
});

test('plot coach agent truncates pathologically long character descriptions at the per-type budget', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create();

    // ~2000 chars — well over the 800-char character budget.
    $huge = str_repeat('A long meandering paragraph about Maja that exceeds the budget. ', 30);

    Character::factory()->for($book)->create([
        'name' => 'Maja',
        'ai_description' => $huge,
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Maja')
        ->toContain('A long meandering paragraph about Maja')
        ->toContain('…');
});

test('plot coach agent guides the coach toward GetEntityDetails for deep prose lookups', function () {
    $book = Book::factory()->create();
    $session = PlotCoachSession::factory()->for($book, 'book')->create([
        'stage' => PlotCoachStage::Plotting,
    ]);

    $agent = new PlotCoachAgent($book, $session);
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Staying consistent with what')
        ->toContain('GetEntityDetails')
        ->toContain('plot_point_ids');
});
