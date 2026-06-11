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
 * attaches a coach session + an authentic-looking 4-turn conversation grounded in the
 * seeded board structure (Acts 1–3 and their plot points/beats).
 * Used for screenshot capture and marketing assets.
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
            'title' => 'Strengthening Act 2 — Institutional Betrayal',
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
                'content' => "Act 2 feels thin to me. \"The Contamination Cover-Up\" is fully fulfilled, but \"Institutional Betrayal\" only has two planned beats and nothing connects it back to the 1987 timeline. I'm worried the middle sags right where the stakes should be climbing.",
            ],
            [
                'role' => 'assistant',
                'minutes_ago' => 5,
                'content' => "You're reading your own board correctly. Look at the asymmetry: \"The Contamination Cover-Up\" carries three fulfilled beats — Reed's reveal, the Aldridge connection, Elena's confrontation with Gerald — while \"Institutional Betrayal\" sits next to it with two planned beats and no chapter anchoring either of them.\n\nStructurally, that turning point is where Act 2 earns its title, The Unraveling. Right now the unraveling happens to Elena in 1987, but not to Harlow in the present. The betrayal plot point is supposed to be Harlow's version of the warning on Elena's windshield — the moment the institution she serves shows its teeth.\n\nTwo questions before we touch the board: who inside the department pressured the original detective in 1987, and is that person — or their successor — still in a position to pressure Harlow now? If the answer is yes, the two timelines stop running in parallel and start colliding.",
            ],
            [
                'role' => 'user',
                'minutes_ago' => 3,
                'content' => 'Yes — I like the idea that the captain who tells Harlow to keep her "caseload numbers presentable" is connected to whoever shut down the original investigation. Maybe he was a junior officer on the Voss case.',
            ],
            [
                'role' => 'assistant',
                'minutes_ago' => 1,
                'content' => "That closes the loop elegantly. The captain's throwaway line in Chapter 1 retroactively becomes a planted setup, and \"Institutional Betrayal\" stops being abstract — it has a face Harlow reports to every morning.\n\nThree structural notes for the board:\n\n1. Add a beat under \"Institutional Betrayal\" where Harlow finds the captain's name in the original 1987 case file — small, procedural, devastating.\n\n2. The existing beat \"Harlow's own department pushes back\" should escalate from passive (stalled paperwork) to active (the Voss file is recalled to storage). Pressure she can't prove is the thriller's best fuel.\n\n3. This also sharpens \"The Reckoning\" in Act 3: Harlow's final confrontation with Kieran Aldridge lands harder if she has already burned her standing in the department to get there.\n\nWant me to draft these beats so you can review and apply them to the board?",
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
