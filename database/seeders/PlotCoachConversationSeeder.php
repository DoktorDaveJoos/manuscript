<?php

namespace Database\Seeders;

use App\Enums\CoachingMode;
use App\Enums\PlotCoachSessionStatus;
use App\Enums\PlotCoachStage;
use App\Models\Book;
use App\Models\PlotCoachSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds an English Plot Coach conversation for the marketing book "The Vanishing Hour".
 *
 * Depends on MarketingSeeder having run first — looks up the book by title and
 * attaches a coach session + an authentic-looking 4-turn conversation about
 * Detective Harlow's character motivation. Used for screenshot capture and marketing assets.
 */
class PlotCoachConversationSeeder extends Seeder
{
    public function run(): void
    {
        $book = Book::query()->where('title', 'The Vanishing Hour')->firstOrFail();

        $conversationId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => null,
            'title' => 'Harlow\'s motivation in Chapter 5',
            'created_at' => $now->subMinutes(6),
            'updated_at' => $now->subSeconds(30),
        ]);

        $session = PlotCoachSession::create([
            'book_id' => $book->id,
            'agent_conversation_id' => $conversationId,
            'status' => PlotCoachSessionStatus::Active,
            'stage' => PlotCoachStage::Refinement,
            'coaching_mode' => CoachingMode::Guided,
            'decisions' => [],
            'pending_board_changes' => [],
            'input_tokens' => 4820,
            'output_tokens' => 1240,
            'cost_cents' => 18,
            'user_turn_count' => 2,
        ]);

        $messages = [
            [
                'role' => 'user',
                'minutes_ago' => 6,
                'content' => "I'm stuck on Harlow's motivation for Chapter 5. After Reed gives her the Aldridge files, I want her to commit fully to the investigation — but I can't tell if that commitment is coming from a place of professional duty or personal obsession. Both feel slightly off.",
            ],
            [
                'role' => 'assistant',
                'minutes_ago' => 5,
                'content' => "That hesitation is worth interrogating. Before we resolve it: what does Harlow lose if she investigates this case fully, and what does she lose if she walks away?\n\nIn Chapter 1 you established her as someone who got Cold Cases as a demotion after the Whitfield investigation. Is the Voss case the thing that redeems her, or the thing that finishes her? The answer changes what kind of commitment makes sense.\n\nA character who fully commits out of obsession needs to have something at stake personally. Right now Harlow is intellectually invested, but the reader doesn't yet feel that the case has hooked something deeper in her. Could there be a parallel between Voss and someone in Harlow's past — not blood relation, but emotional pattern?",
            ],
            [
                'role' => 'user',
                'minutes_ago' => 3,
                'content' => "That's good. Maybe Harlow's sister or a close friend disappeared briefly when she was young — not a true crime, but enough that \"voluntary disappearance\" as an explanation has always rung false to her.",
            ],
            [
                'role' => 'assistant',
                'minutes_ago' => 1,
                'content' => "That works. Now it isn't just a case — it's a wound being reopened. Three craft notes:\n\n1. Plant it gently in Chapter 1. A single line, no flashback. Show the reader Harlow notices the words \"voluntary disappearance\" before she reads them. Let them feel her resistance without being told why.\n\n2. Reveal the personal connection in Chapter 4, after we've watched her get obsessed but before the Aldridge confrontation. The reader should think \"oh\" — not \"aha\".\n\n3. Don't resolve it. The case can close. The wound stays open. That's the kind of ending that earns its emotional weight.\n\nWant me to add a beat to Plot Point 1 (Cold Case Reopened) tracking this personal motif, or leave it for you to draft into the chapter directly?",
            ],
        ];

        $rows = [];
        foreach ($messages as $message) {
            $timestamp = $now->subMinutes($message['minutes_ago']);

            $rows[] = [
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversationId,
                'user_id' => null,
                'agent' => 'plot-coach',
                'role' => $message['role'],
                'content' => $message['content'],
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '{}',
                'meta' => '{}',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DB::table('agent_conversation_messages')->insert($rows);

        $this->command?->info("Plot Coach conversation seeded (session {$session->id}, conversation {$conversationId}).");
    }
}
