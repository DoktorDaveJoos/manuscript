<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPreparation extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_chapters' => 'integer',
            'processed_chapters' => 'integer',
            'embedded_chunks' => 'integer',
            'current_phase_total' => 'integer',
            'current_phase_progress' => 'integer',
            'completed_phases' => 'array',
            'phase_errors' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Atomically append a phase error (refreshes from DB to avoid stale overwrites).
     */
    public function appendPhaseError(string $phase, ?string $chapter, string $error): void
    {
        $this->refresh();
        $errors = $this->phase_errors ?? [];
        $errors[] = ['phase' => $phase, 'chapter' => $chapter, 'error' => $error];
        $this->update(['phase_errors' => $errors]);
    }

    /**
     * Merge phases into completed_phases (refreshes from DB to avoid stale overwrites).
     *
     * @param  list<string>  $phases
     */
    public function markPhasesCompleted(array $phases): void
    {
        $this->refresh();
        $existing = $this->completed_phases ?? [];
        $this->update([
            'completed_phases' => array_values(array_unique(array_merge($existing, $phases))),
        ]);
    }
}
