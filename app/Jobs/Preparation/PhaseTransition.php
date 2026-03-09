<?php

namespace App\Jobs\Preparation;

use App\Models\AiPreparation;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PhaseTransition implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 10;

    /**
     * @param  list<string>  $completedPhases
     */
    public function __construct(
        private AiPreparation $preparation,
        private ?string $startPhase = null,
        private int $phaseTotal = 0,
        private array $completedPhases = [],
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if (! empty($this->completedPhases)) {
            $this->preparation->markPhasesCompleted($this->completedPhases);
        }

        if ($this->startPhase) {
            $this->preparation->update([
                'current_phase' => $this->startPhase,
                'current_phase_total' => $this->phaseTotal,
                'current_phase_progress' => 0,
            ]);
        }
    }
}
