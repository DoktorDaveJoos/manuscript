<?php

namespace App\Ai\Tools\Plot;

use App\Enums\PlotCoachSessionStatus;
use App\Models\PlotCoachSession;
use App\Services\PlotCoachBatchService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Reverts the most recent applied batch in the active session.
 *
 * Never silent — only called when the user explicitly asks.
 */
class UndoLastBatch implements Tool
{
    public function __construct(private ?PlotCoachBatchService $service = null)
    {
        $this->service = $service ?? new PlotCoachBatchService;
    }

    public function description(): Stringable|string
    {
        return 'Reverses the most recent applied batch in the current session. Use when the user explicitly asks to undo or take back. Never silently undo.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'book_id' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $bookId = $request['book_id'] ?? null;

        if (! is_int($bookId)) {
            return 'Undo failed: invalid arguments.';
        }

        $session = $this->resolveSession($bookId);

        if (! $session) {
            return "Undo failed: no active plot coach session for book {$bookId}.";
        }

        try {
            $batch = $this->service->undo($session);
        } catch (Throwable $e) {
            return "Undo failed: {$e->getMessage()}.";
        }

        if (! $batch) {
            return 'No batch to undo in this session.';
        }

        return "Undone batch #{$batch->id}: {$batch->summary}.";
    }

    private function resolveSession(int $bookId): ?PlotCoachSession
    {
        return PlotCoachSession::query()
            ->where('book_id', $bookId)
            ->where('status', PlotCoachSessionStatus::Active)
            ->first();
    }
}
