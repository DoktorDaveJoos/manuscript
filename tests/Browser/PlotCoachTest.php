<?php

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

    // Default mode is Board (no active coach session). Switch to Coach.
    $page->click('Coach');

    $page->assertSee('Configure AI');
});

it('shows the intake empty state in Coach mode when Pro + AI configured', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    $page->click('Coach');

    // The intake opener should render with the welcome headline + body.
    $page->assertSee('Hi.');
    $page->assertSee("I'll be your plot coach");
});

it('allows typing a first message into the intake input bar', function () {
    License::factory()->create();
    $book = Book::factory()->withAi()->create();

    $page = visit("/books/{$book->id}/plot");

    $page->assertNoJavaScriptErrors();

    $page->click('Coach');

    // Intake input is enabled — fill via CSS selector (no submit, since SSE
    // is hard to exercise under the browser harness).
    $page->fill('input[type="text"]', 'A mystery novel set in 1920s Berlin');
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

    // Default is Board — coach intake should not be visible.
    $page->assertDontSee('Hi.');

    // Switch to Coach.
    $page->click('Coach');
    $page->assertSee('Hi.');

    // Switch back to Board.
    $page->click('Board');
    $page->assertDontSee('Hi.');
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

// No-Pro redirect happens at middleware level (see PlotCoachControllerTest
// feature test for the 403). Browser tests can't reliably test that due to
// RefreshDatabase transaction isolation — same reason noted in
// EditorialReviewTest.php.
