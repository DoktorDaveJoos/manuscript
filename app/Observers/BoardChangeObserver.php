<?php

namespace App\Observers;

use App\Models\Act;
use App\Models\Beat;
use App\Models\PlotCoachSession;
use App\Models\PlotPoint;
use App\Models\Storyline;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer that queues board mutations onto the active Plot Coach session.
 *
 * Listens to `created`, `updated`, and `deleted` lifecycle events on
 * PlotPoint, Beat, Storyline, and Act. Each mutation is appended to the
 * active session's `pending_board_changes` JSON. On the next stream turn,
 * the controller flushes the queue as a system note to the AI so the coach
 * can acknowledge user-driven board edits.
 *
 * Writes performed by the AI itself (via PlotCoachBatchService) should NOT
 * feed back into the queue — wrap those writes in `suppress(...)` so the
 * observer is a no-op for the duration of the callable.
 */
class BoardChangeObserver
{
    private static bool $suppressed = false;

    /**
     * Run a callback with observer dispatch suppressed.
     *
     * Used by PlotCoachBatchService to prevent AI-initiated writes from
     * appearing in the board-change queue — the AI already knows what it
     * just wrote.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function suppress(callable $callback): mixed
    {
        $previous = self::$suppressed;
        self::$suppressed = true;

        try {
            return $callback();
        } finally {
            self::$suppressed = $previous;
        }
    }

    public function created(Model $model): void
    {
        $this->queue($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->queue($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->queue($model, 'deleted');
    }

    private function queue(Model $model, string $kind): void
    {
        if (self::$suppressed) {
            return;
        }

        [$type, $bookId] = $this->resolveTypeAndBook($model);

        if ($type === null || $bookId === null) {
            return;
        }

        $session = PlotCoachSession::activeForBook($bookId);

        if (! $session) {
            return;
        }

        $changes = $session->pending_board_changes ?? [];
        $changes[] = [
            'kind' => $kind,
            'type' => $type,
            'id' => $model->getKey(),
            'summary' => $this->summarize($model, $type, $kind),
            'at' => now()->toIso8601String(),
        ];

        PlotCoachSession::query()
            ->where('id', $session->id)
            ->update(['pending_board_changes' => $changes]);
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private function resolveTypeAndBook(Model $model): array
    {
        return match (true) {
            $model instanceof PlotPoint => ['plot_point', $model->book_id],
            $model instanceof Beat => ['beat', $this->beatBookId($model)],
            $model instanceof Storyline => ['storyline', $model->book_id],
            $model instanceof Act => ['act', $model->book_id],
            default => [null, null],
        };
    }

    private function beatBookId(Beat $beat): ?int
    {
        if (! $beat->plot_point_id) {
            return null;
        }

        return PlotPoint::query()
            ->where('id', $beat->plot_point_id)
            ->value('book_id');
    }

    private function summarize(Model $model, string $type, string $kind): string
    {
        $label = match ($type) {
            'plot_point' => 'Plot point',
            'beat' => 'Beat',
            'storyline' => 'Storyline',
            'act' => 'Act',
            default => 'Item',
        };

        $name = $this->displayName($model, $type);

        $summary = $name !== ''
            ? "{$label} '{$name}' {$kind}"
            : "{$label} {$kind}";

        return mb_substr($summary, 0, 100);
    }

    private function displayName(Model $model, string $type): string
    {
        return match ($type) {
            'plot_point', 'beat', 'act' => (string) ($model->title ?? ''),
            'storyline' => (string) ($model->name ?? ''),
            default => '',
        };
    }
}
