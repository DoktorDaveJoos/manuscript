<?php

namespace App\Services;

use App\Enums\BeatStatus;
use App\Enums\PlotPointStatus;
use App\Enums\PlotPointType;
use App\Enums\StorylineType;
use App\Enums\WikiEntryKind;
use App\Models\Beat;
use App\Models\Character;
use App\Models\PlotCoachBatch;
use App\Models\PlotCoachSession;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Models\WikiEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Transactional engine for Plot Coach batch apply + undo.
 *
 * Writes are creates only in v1. Partial-apply is disallowed:
 * any failure rolls the entire batch back.
 */
class PlotCoachBatchService
{
    private const UNDO_WINDOW_MINUTES = 30;

    /**
     * Apply a batch of writes for the given session.
     *
     * @param  array<int, array{type: string, data: array<string, mixed>}>  $writes
     */
    public function apply(PlotCoachSession $session, array $writes, string $summary): PlotCoachBatch
    {
        return DB::transaction(function () use ($session, $writes, $summary) {
            $persisted = [];

            foreach ($writes as $write) {
                $persisted[] = $this->dispatch($session, $write);
            }

            return PlotCoachBatch::create([
                'session_id' => $session->id,
                'summary' => $summary,
                'payload' => ['version' => 1, 'writes' => $persisted],
                'applied_at' => now(),
                'undo_window_expires_at' => now()->addMinutes(self::UNDO_WINDOW_MINUTES),
            ]);
        });
    }

    /**
     * Undo the most recent un-reverted batch in the session.
     *
     * Returns the reverted batch, or null if there is nothing to undo.
     */
    public function undo(PlotCoachSession $session): ?PlotCoachBatch
    {
        $batch = PlotCoachBatch::query()
            ->where('session_id', $session->id)
            ->whereNull('reverted_at')
            ->orderByDesc('id')
            ->first();

        if (! $batch) {
            return null;
        }

        return DB::transaction(function () use ($batch) {
            $writes = $batch->payload['writes'] ?? [];

            foreach (array_reverse($writes) as $write) {
                $this->deleteWrite($write);
            }

            $batch->update(['reverted_at' => now()]);

            return $batch->fresh();
        });
    }

    /**
     * Dispatch a single write to its handler and return the persisted record
     * descriptor for the batch payload.
     *
     * @param  array{type?: string, data?: array<string, mixed>}  $write
     * @return array{type: string, id: int}
     */
    private function dispatch(PlotCoachSession $session, array $write): array
    {
        if (! isset($write['type']) || ! isset($write['data']) || ! is_array($write['data'])) {
            throw new InvalidArgumentException('Each write must have `type` and `data` keys.');
        }

        $type = $write['type'];
        $data = $write['data'];

        return match ($type) {
            'character' => $this->writeCharacter($session, $data),
            'wiki_entry' => $this->writeWikiEntry($session, $data),
            'storyline' => $this->writeStoryline($session, $data),
            'plot_point' => $this->writePlotPoint($session, $data),
            'beat' => $this->writeBeat($session, $data),
            default => throw new InvalidArgumentException("Unknown write type: {$type}"),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int}
     */
    private function writeCharacter(PlotCoachSession $session, array $data): array
    {
        $this->requireString($data, 'name', 'character');

        $character = Character::query()->create([
            'book_id' => $session->book_id,
            'name' => $data['name'],
            'ai_description' => $data['ai_description'] ?? null,
            'is_ai_extracted' => (bool) ($data['is_ai_extracted'] ?? true),
        ]);

        return ['type' => 'character', 'id' => $character->id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int}
     */
    private function writeWikiEntry(PlotCoachSession $session, array $data): array
    {
        $this->requireString($data, 'name', 'wiki_entry');
        $this->requireString($data, 'kind', 'wiki_entry');

        $kind = $this->enum(WikiEntryKind::class, $data['kind'], 'wiki_entry.kind');

        $entry = WikiEntry::query()->create([
            'book_id' => $session->book_id,
            'kind' => $kind->value,
            'name' => $data['name'],
            'ai_description' => $data['ai_description'] ?? null,
            'is_ai_extracted' => (bool) ($data['is_ai_extracted'] ?? true),
        ]);

        return ['type' => 'wiki_entry', 'id' => $entry->id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int}
     */
    private function writeStoryline(PlotCoachSession $session, array $data): array
    {
        $this->requireString($data, 'name', 'storyline');

        $type = isset($data['type'])
            ? $this->enum(StorylineType::class, $data['type'], 'storyline.type')
            : StorylineType::Main;

        $storyline = Storyline::query()->create([
            'book_id' => $session->book_id,
            'name' => $data['name'],
            'type' => $type->value,
            'color' => $data['color'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return ['type' => 'storyline', 'id' => $storyline->id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int}
     */
    private function writePlotPoint(PlotCoachSession $session, array $data): array
    {
        $this->requireString($data, 'title', 'plot_point');

        if (empty($data['act_id'])) {
            throw new InvalidArgumentException('plot_point.act_id is required.');
        }

        $type = isset($data['type'])
            ? $this->enum(PlotPointType::class, $data['type'], 'plot_point.type')
            : PlotPointType::Setup;

        $status = isset($data['status'])
            ? $this->enum(PlotPointStatus::class, $data['status'], 'plot_point.status')
            : PlotPointStatus::Planned;

        $plotPoint = PlotPoint::query()->create([
            'book_id' => $session->book_id,
            'act_id' => (int) $data['act_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $type->value,
            'status' => $status->value,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        if (! empty($data['character_ids']) && is_array($data['character_ids'])) {
            $plotPoint->characters()->attach(array_map('intval', $data['character_ids']));
        }

        return ['type' => 'plot_point', 'id' => $plotPoint->id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int}
     */
    private function writeBeat(PlotCoachSession $session, array $data): array
    {
        $this->requireString($data, 'title', 'beat');

        if (empty($data['plot_point_id'])) {
            throw new InvalidArgumentException('beat.plot_point_id is required.');
        }

        // Ensure the plot_point belongs to this session's book so AI can't splice
        // beats onto another book.
        $plotPointBookId = PlotPoint::query()
            ->where('id', $data['plot_point_id'])
            ->value('book_id');

        if ($plotPointBookId !== $session->book_id) {
            throw new InvalidArgumentException('beat.plot_point_id does not belong to this book.');
        }

        $status = isset($data['status'])
            ? $this->enum(BeatStatus::class, $data['status'], 'beat.status')
            : BeatStatus::Planned;

        $beat = Beat::query()->create([
            'plot_point_id' => (int) $data['plot_point_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $status->value,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return ['type' => 'beat', 'id' => $beat->id];
    }

    /**
     * Delete a single persisted record from a batch payload. Missing rows are
     * skipped silently (user may have deleted them manually). Any other
     * deletion failure bubbles up.
     *
     * @param  array{type?: string, id?: int}  $write
     */
    private function deleteWrite(array $write): void
    {
        if (! isset($write['type']) || ! isset($write['id'])) {
            return;
        }

        $model = match ($write['type']) {
            'character' => Character::query()->find($write['id']),
            'wiki_entry' => WikiEntry::query()->find($write['id']),
            'storyline' => Storyline::query()->find($write['id']),
            'plot_point' => PlotPoint::query()->find($write['id']),
            'beat' => Beat::query()->find($write['id']),
            default => null,
        };

        if (! $model) {
            return;
        }

        $model->delete();
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     */
    private function enum(string $enum, mixed $value, string $field): \BackedEnum
    {
        try {
            /** @var \BackedEnum $case */
            $case = $enum::from($value);

            return $case;
        } catch (\ValueError) {
            throw new InvalidArgumentException("Invalid value for {$field}: ".var_export($value, true));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requireString(array $data, string $key, string $type): void
    {
        if (empty($data[$key]) || ! is_string($data[$key])) {
            throw new InvalidArgumentException("{$type}.{$key} is required and must be a non-empty string.");
        }
    }
}
