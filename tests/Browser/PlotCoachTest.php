<?php

use App\Models\Act;
use App\Models\Book;
use App\Models\License;
use App\Models\PlotCoachSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('shows the configure-AI CTA in Coach mode when Pro but no AI provider', function () {
    License::factory()->create();
    $book = Book::factory()->create();

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    // Coach is the default mode — the CTA renders without any toggle click.
    $page->assertSee('Configure AI');
});

it('shows the intake empty state in Coach mode when Pro + AI configured', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    // The intake opener should render with the welcome headline + body.
    $page->assertSee('Hi.');
    $page->assertSee("I'll be your plot coach");
});

it('allows typing a first message into the intake input bar', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    // Intake input is enabled — fill the AiChatInput textarea via CSS
    // selector (no submit, since SSE is hard to exercise under the browser
    // harness).
    $page->fill('textarea[aria-label="Message Coach…"]', 'A mystery novel set in 1920s Berlin');
});

it('hydrates prior messages when an active coach session exists', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    $session = PlotCoachSession::factory()->create(['book_id' => $book->id]);

    // Seed historical messages directly onto the underlying agent_conversation.
    $seed = fn (string $role, string $content, int $minAgo) => [
        'id' => (string) Str::uuid(),
        'conversation_id' => $session->agent_conversation_id,
        'user_id' => null,
        'agent' => 'plot-coach',
        'role' => $role,
        'content' => $content,
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '{}',
        'meta' => '{}',
        'created_at' => now()->subMinutes($minAgo),
        'updated_at' => now()->subMinutes($minAgo),
    ];

    DB::table('agent_conversation_messages')->insert([
        $seed('user', 'I want to write a noir mystery.', 2),
        $seed('assistant', 'Great. Tell me about your detective.', 1),
    ]);

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    // An active session means Coach is the default mode already.
    $page->assertSee('I want to write a noir mystery.');
    $page->assertSee('Great. Tell me about your detective.');
});

it('renders both mode toggle buttons on the plot page', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors()
        ->assertSee('Coach')
        ->assertSee('Board');
});

it('toggles between coach and board modes via the toggle', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    // Default is Coach — the intake opener is visible immediately.
    $page->assertSee('Hi.');

    // Switch to Board.
    $page->click('Board');
    $page->assertDontSee('Hi.');

    // Switch back to Coach.
    $page->click('Coach');
    $page->assertSee('Hi.');
});

it('fetches the latest plot state when switching to the board', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    // Created AFTER the page load — exactly what happens when the coach
    // applies a batch through the streamed chat: the Inertia page props on
    // the client are stale by the time the user opens the board.
    Act::factory()->create([
        'book_id' => $book->id,
        'number' => 1,
        'title' => 'Midnight Heist Act',
    ]);

    $page->click('Board');

    // Switching to Board must refetch from the server, not render stale props.
    $page->assertSee('Midnight Heist Act');
});

it('renders a batch proposal card when the assistant message contains a sentinel', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    $session = PlotCoachSession::factory()->create(['book_id' => $book->id]);

    $proposalId = (string) Str::uuid();
    $payload = json_encode([
        'proposal_id' => $proposalId,
        'summary' => 'Seed core cast',
        'writes' => [
            ['type' => 'character', 'data' => ['name' => 'Mara', 'ai_description' => 'A medic.']],
            ['type' => 'storyline', 'data' => ['name' => 'Resistance arc', 'type' => 'main']],
        ],
    ]);

    $assistantContent = "Here's the preview:\n\n<!-- PLOT_COACH_BATCH_PROPOSAL\n{$payload}\n-->";

    $seed = fn (string $role, string $content, int $minAgo) => [
        'id' => (string) Str::uuid(),
        'conversation_id' => $session->agent_conversation_id,
        'user_id' => null,
        'agent' => 'plot-coach',
        'role' => $role,
        'content' => $content,
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '{}',
        'meta' => '{}',
        'created_at' => now()->subMinutes($minAgo),
        'updated_at' => now()->subMinutes($minAgo),
    ];

    DB::table('agent_conversation_messages')->insert([
        $seed('user', 'Let us seed the cast.', 2),
        $seed('assistant', $assistantContent, 1),
    ]);

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    // Card renders with its title + the character/storyline names.
    $page->assertSee('Batch preview');
    $page->assertSee('Approve all');
    $page->assertSee('Mara');
    $page->assertSee('Resistance arc');

    // Sentinel marker itself is stripped from the rendered output.
    $page->assertDontSee('PLOT_COACH_BATCH_PROPOSAL');
});

it('shows an undo button in the top bar when a coach session is active', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    PlotCoachSession::factory()->create(['book_id' => $book->id]);

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    // Active session → default mode is Coach → undo button visible.
    $page->assertSee('Undo last batch');
});

it('toggles the coach insights panel via the access bar', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    PlotCoachSession::factory()->create(['book_id' => $book->id]);

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    // Active session → coach mode is the default → insights panel open.
    $page->assertSee('What the coach can see');

    // Close via the access bar icon.
    $page->click('[data-access-bar="coach-insights"]')
        ->wait(1)
        ->assertDontSee('What the coach can see');

    // Reopen via the same icon.
    $page->click('[data-access-bar="coach-insights"]')
        ->wait(1)
        ->assertSee('What the coach can see');
});

it('does not render the coach insights panel in board mode', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    // Coach is the default mode, so switch to Board first.
    $page->click('Board')
        ->wait(1)
        ->assertDontSee('What the coach can see');
});

it('shows onboarding hints in the insights panel when the board is empty', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    PlotCoachSession::factory()->create(['book_id' => $book->id]);

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    // Empty board → the panel invites instead of assuming structure exists.
    $page->assertSee('What can you do for me?');
    $page->assertDontSee('Turn the approved beats into chapter stubs.');
});

it('shows working hints in the insights panel once the board has structure', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    PlotCoachSession::factory()->create(['book_id' => $book->id]);

    Act::factory()->create(['book_id' => $book->id, 'number' => 1, 'title' => 'Act I']);

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    $page->assertSee('Turn the approved beats into chapter stubs.');
    $page->assertDontSee('What can you do for me?');
});

// No-Pro redirect happens at middleware level (see PlotCoachControllerTest
// feature test for the 403). Browser tests can't reliably test that due to
// RefreshDatabase transaction isolation — same reason noted in
// EditorialReviewTest.php.
