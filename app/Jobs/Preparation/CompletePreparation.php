<?php

namespace App\Jobs\Preparation;

use App\Models\AiPreparation;
use App\Models\Book;
use App\Models\HealthSnapshot;
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

        $chapters = $this->book->chapters()->get();
        $this->upsertHealthSnapshot($chapters);

        $this->preparation->markPhasesCompleted(['health_analysis']);
        $this->preparation->refresh();

        $this->preparation->update([
            'status' => 'completed',
            'phase_errors' => $this->preparation->phase_errors ?: null,
        ]);
    }

    /**
     * @param  Collection<int, \App\Models\Chapter>  $chapters
     */
    private function upsertHealthSnapshot(Collection $chapters): void
    {
        $analyzed = $chapters->filter(fn ($ch) => $ch->hook_score !== null);

        if ($analyzed->isEmpty()) {
            return;
        }

        $avgHook = $analyzed->avg('hook_score');
        $hookScore = min(100, max(0, (int) round($avgHook * 10)));

        $wordCounts = $chapters->pluck('word_count')->filter(fn ($w) => $w > 0);
        if ($wordCounts->count() > 1) {
            $mean = $wordCounts->avg();
            $variance = $wordCounts->map(fn ($w) => pow($w - $mean, 2))->avg();
            $cv = $mean > 0 ? sqrt($variance) / $mean : 0;
            $pacingScore = min(100, max(0, (int) round(100 - abs($cv - 0.25) * 200)));
        } else {
            $pacingScore = 50;
        }

        $tensionChapters = $analyzed->filter(fn ($ch) => $ch->tension_score !== null);
        $tensionScore = $tensionChapters->count() > 2
            ? min(100, max(0, (int) round($tensionChapters->avg('tension_score') * 10)))
            : 50;

        $storylineCounts = $chapters->groupBy('storyline_id')->map->count();
        if ($storylineCounts->count() > 1) {
            $weaveScore = min(100, max(0, (int) round(($storylineCounts->min() / $storylineCounts->max()) * 100)));
        } else {
            $weaveScore = 75;
        }

        $compositeScore = (int) round(
            $hookScore * 0.35 + $pacingScore * 0.25 + $tensionScore * 0.25 + $weaveScore * 0.15
        );

        HealthSnapshot::query()->upsert(
            [
                'book_id' => $this->book->id,
                'recorded_at' => now()->toDateString(),
                'composite_score' => $compositeScore,
                'hooks_score' => $hookScore,
                'pacing_score' => $pacingScore,
                'tension_score' => $tensionScore,
                'weave_score' => $weaveScore,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['book_id', 'recorded_at'],
            ['composite_score', 'hooks_score', 'pacing_score', 'tension_score', 'weave_score', 'updated_at'],
        );
    }
}
