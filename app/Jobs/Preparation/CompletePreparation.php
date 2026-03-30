<?php

namespace App\Jobs\Preparation;

use App\Models\AiPreparation;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\HealthSnapshot;
use App\Services\HealthScoreCalculator;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class CompletePreparation implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        private Book $book,
        private AiPreparation $preparation,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $chapters = $this->book->chapters()
            ->select([
                'id', 'book_id', 'reader_order', 'hook_score', 'hook_type',
                'scene_purpose', 'value_shift', 'pacing_feel', 'tension_score',
                'micro_tension_score', 'exit_hook_score', 'entry_hook_score',
                'emotional_shift_magnitude', 'sensory_grounding', 'information_delivery',
            ])
            ->get();

        $this->upsertHealthSnapshot($chapters);

        $this->preparation->markPhasesCompleted(['health_analysis']);
        $this->preparation->refresh();

        $this->preparation->update([
            'status' => 'completed',
            'phase_errors' => $this->preparation->phase_errors ?: null,
        ]);
    }

    /**
     * @param  Collection<int, Chapter>  $chapters
     */
    private function upsertHealthSnapshot(Collection $chapters): void
    {
        $analyzed = $chapters->filter(fn ($ch) => $ch->hook_score !== null);

        if ($analyzed->isEmpty()) {
            return;
        }

        $scores = (new HealthScoreCalculator($analyzed))->calculate();

        HealthSnapshot::query()->upsert(
            [
                'book_id' => $this->book->id,
                'recorded_at' => now()->toDateString(),
                'composite_score' => $scores['composite'],
                'hooks_score' => $scores['hooks'],
                'pacing_score' => $scores['pacing'],
                'tension_score' => $scores['tension_dynamics'],
                'weave_score' => 0,
                'scene_purpose_score' => $scores['scene_purpose'],
                'tension_dynamics_score' => $scores['tension_dynamics'],
                'emotional_arc_score' => $scores['emotional_arc'],
                'craft_score' => $scores['craft'],
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['book_id', 'recorded_at'],
            ['composite_score', 'hooks_score', 'pacing_score', 'tension_score', 'weave_score', 'scene_purpose_score', 'tension_dynamics_score', 'emotional_arc_score', 'craft_score', 'updated_at'],
        );
    }
}
