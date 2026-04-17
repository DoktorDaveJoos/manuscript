<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AiPreparation extends Model
{
    use HasFactory;

    public const CIRCUIT_BREAKER_THRESHOLD = 3;

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
            'consecutive_failures' => 'integer',
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
     * Atomically append a phase error using a transaction to avoid race conditions.
     *
     * Accepts a Chapter model or a label string (for non-chapter-specific errors).
     * Storing the chapter_id enables targeted retries from the UI.
     */
    public function appendPhaseError(string $phase, Chapter|string|null $chapter, string $error): void
    {
        $chapterId = $chapter instanceof Chapter ? $chapter->id : null;
        $chapterLabel = match (true) {
            $chapter instanceof Chapter => $chapter->title,
            is_string($chapter) => $chapter,
            default => null,
        };

        DB::transaction(function () use ($phase, $chapterId, $chapterLabel, $error) {
            $record = self::query()->lockForUpdate()->find($this->id);
            $errors = $record->phase_errors ?? [];
            $errors[] = [
                'phase' => $phase,
                'chapter' => $chapterLabel,
                'chapter_id' => $chapterId,
                'error' => $error,
            ];
            $record->update(['phase_errors' => $errors]);
        });

        $this->refresh();
    }

    /**
     * Remove phase-error entries matching the given phase + chapter_id pairs.
     * Used when the user retries specific failures.
     *
     * @param  list<array{phase: string, chapter_id: ?int}>  $matches
     */
    public function clearPhaseErrors(array $matches): void
    {
        DB::transaction(function () use ($matches) {
            $record = self::query()->lockForUpdate()->find($this->id);
            $remaining = collect($record->phase_errors ?? [])
                ->reject(function (array $entry) use ($matches) {
                    foreach ($matches as $match) {
                        if (
                            ($entry['phase'] ?? null) === $match['phase']
                            && ($entry['chapter_id'] ?? null) === $match['chapter_id']
                        ) {
                            return true;
                        }
                    }

                    return false;
                })
                ->values()
                ->all();

            $record->update(['phase_errors' => $remaining]);
        });

        $this->refresh();
    }

    /**
     * Atomically merge phases into completed_phases using a transaction to avoid race conditions.
     *
     * @param  list<string>  $phases
     */
    public function markPhasesCompleted(array $phases): void
    {
        DB::transaction(function () use ($phases) {
            $record = self::query()->lockForUpdate()->find($this->id);
            $existing = $record->completed_phases ?? [];
            $record->update([
                'completed_phases' => array_values(array_unique(array_merge($existing, $phases))),
            ]);
        });

        $this->refresh();
    }

    /**
     * Check if the circuit breaker has tripped (3+ consecutive failures).
     */
    public function shouldCircuitBreak(): bool
    {
        return $this->consecutive_failures >= self::CIRCUIT_BREAKER_THRESHOLD;
    }

    /**
     * Atomically record a consecutive failure. Returns the new count.
     */
    public function recordConsecutiveFailure(): int
    {
        $newCount = DB::transaction(function () {
            $record = self::query()->lockForUpdate()->find($this->id);
            $newCount = ($record->consecutive_failures ?? 0) + 1;
            $record->update(['consecutive_failures' => $newCount]);

            return $newCount;
        });

        $this->refresh();

        return $newCount;
    }

    /**
     * Reset the consecutive failure counter on success.
     */
    public function resetConsecutiveFailures(): void
    {
        $this->update(['consecutive_failures' => 0]);
    }
}
