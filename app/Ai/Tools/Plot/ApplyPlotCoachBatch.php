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
 * Commits a previously-proposed batch to the DB in a single transaction.
 *
 * On failure the whole batch rolls back — the agent is expected to re-propose
 * with the conflict resolved.
 */
class ApplyPlotCoachBatch implements Tool
{
    public function __construct(private ?PlotCoachBatchService $service = null)
    {
        $this->service = $service ?? new PlotCoachBatchService;
    }

    public function description(): Stringable|string
    {
        return 'Applies a previously-proposed batch of writes to the book. The user must have explicitly approved the proposal in chat before you call this. On success, returns a confirmation. On failure (e.g., name collision), the entire batch rolls back and you should re-propose with the conflict resolved.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'book_id' => $schema->integer()->required(),
            'writes' => $schema->array()->required(),
            'summary' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $bookId = $request['book_id'] ?? null;
        $writes = $request['writes'] ?? [];
        $summary = (string) ($request['summary'] ?? '');

        if (! is_int($bookId) || ! is_array($writes)) {
            return 'Batch failed: invalid arguments. Nothing persisted.';
        }

        $session = $this->resolveSession($bookId);

        if (! $session) {
            return "Batch failed: no active plot coach session for book {$bookId}. Nothing persisted.";
        }

        try {
            $batch = $this->service->apply($session, $writes, $summary);
        } catch (Throwable $e) {
            return "Batch failed: {$e->getMessage()}. Nothing persisted. Propose a revised batch.";
        }

        $count = count($batch->payload['writes'] ?? []);

        return "Applied batch #{$batch->id}: {$summary}. {$count} item".($count === 1 ? '' : 's').' written.';
    }

    private function resolveSession(int $bookId): ?PlotCoachSession
    {
        return PlotCoachSession::query()
            ->where('book_id', $bookId)
            ->where('status', PlotCoachSessionStatus::Active)
            ->first();
    }
}
