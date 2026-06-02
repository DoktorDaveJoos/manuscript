<?php

namespace Database\Factories;

use App\Enums\PlotCoachSessionStatus;
use App\Enums\PlotCoachStage;
use App\Models\Book;
use App\Models\PlotCoachSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlotCoachSession>
 */
class PlotCoachSessionFactory extends Factory
{
    protected $model = PlotCoachSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $conversationId = (string) Str::uuid();

        DB::table('agent_conversations')->insert([
            'id' => $conversationId,
            'user_id' => null,
            'title' => 'test conversation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'book_id' => Book::factory(),
            'agent_conversation_id' => $conversationId,
            'status' => PlotCoachSessionStatus::Active,
            'stage' => PlotCoachStage::Intake,
            'coaching_mode' => null,
            'decisions' => [],
            'pending_board_changes' => [],
            'input_tokens' => null,
            'output_tokens' => null,
            'cost_cents' => null,
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => PlotCoachSessionStatus::Archived,
            'archived_at' => now(),
        ]);
    }
}
