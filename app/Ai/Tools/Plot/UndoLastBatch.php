<?php

namespace App\Ai\Tools\Plot;

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
    /**
     * The session is bound by the agent so undo targets the conversation
     * being streamed — even when it is not the book's active session. Falls
     * back to the active session for direct construction (tests, legacy).
     */
    public function __construct(
        private int $bookId,
        private PlotCoachBatchService $service = new PlotCoachBatchService,
        private ?PlotCoachSession $session = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Reverses the most recent applied batch in the current session. Use when the user explicitly asks to undo or take back. Never silently undo.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        $session = $this->session ?? PlotCoachSession::activeForBook($this->bookId);

        if (! $session) {
            return "Undo failed: no active plot coach session for book {$this->bookId}.";
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
}
