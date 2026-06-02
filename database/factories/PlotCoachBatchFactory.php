<?php

namespace Database\Factories;

use App\Models\PlotCoachBatch;
use App\Models\PlotCoachSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlotCoachBatch>
 */
class PlotCoachBatchFactory extends Factory
{
    protected $model = PlotCoachBatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => PlotCoachSession::factory(),
            'summary' => 'test batch',
            'payload' => [],
            'applied_at' => now(),
            'reverted_at' => null,
            'undo_window_expires_at' => now()->addMinutes(5),
        ];
    }
}
