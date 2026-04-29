<?php

namespace App\Services;

use App\Enums\BeatStatus;
use App\Enums\ChapterStatus;
use App\Enums\CoachingMode;
use App\Enums\Genre;
use App\Enums\PlotCoachStage;
use App\Enums\PlotPointStatus;
use App\Enums\PlotPointType;
use App\Enums\StorylineType;
use App\Enums\VersionSource;
use App\Enums\WikiEntryKind;
use App\Models\Act;
use App\Models\Beat;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\PlotCoachBatch;
use App\Models\PlotCoachSession;
use App\Models\PlotPoint;
use App\Models\Storyline;
use App\Models\WikiEntry;
use App\Observers\BoardChangeObserver;
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
        return BoardChangeObserver::suppress(function () use ($session, $writes, $summary) {
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

        return $this->undoBatch($batch);
    }

    /**
     * Revert a specific batch. Idempotent: if the batch is already reverted
     * returns it unchanged; if it doesn't exist returns null.
     */
    public function undoBatch(PlotCoachBatch $batch): ?PlotCoachBatch
    {
        if ($batch->reverted_at) {
            return $batch;
        }

        return BoardChangeObserver::suppress(function () use ($batch) {
            return DB::transaction(function () use ($batch) {
                $writes = $batch->payload['writes'] ?? [];

                foreach (array_reverse($writes) as $write) {
                    $this->deleteWrite($write);
                }

                $batch->update(['reverted_at' => now()]);

                return $batch->fresh();
            });
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
            'chapter' => $this->writeChapter($session, $data),
            'act' => $this->writeAct($session, $data),
            'book_update' => $this->writeBookUpdate($session, $data),
            'session_update' => $this->writeSessionUpdate($session, $data),
            'delete' => $this->writeDelete($session, $data),
            default => throw new InvalidArgumentException("Unknown write type: {$type}"),
        };
    }

    /**
     * Soft-delete an existing entity. The persisted descriptor records the
     * target so undo can `restore()` the row.
     *
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int, target: string, deleted: true}
     */
    private function writeDelete(PlotCoachSession $session, array $data): array
    {
        $target = isset($data['target']) ? (string) $data['target'] : '';
        $allowed = ['character', 'wiki_entry', 'storyline', 'plot_point', 'beat', 'chapter', 'act'];

        if (! in_array($target, $allowed, true)) {
            throw new InvalidArgumentException(
                'delete.target must be one of: '.implode(', ', $allowed).'.'
            );
        }

        if (! isset($data['id']) || ! is_numeric($data['id'])) {
            throw new InvalidArgumentException('delete.id is required and must be an integer.');
        }

        $id = (int) $data['id'];
        $bookId = $session->book_id;

        $model = match ($target) {
            'character' => Character::query()->where('id', $id)->where('book_id', $bookId)->first(),
            'wiki_entry' => WikiEntry::query()->where('id', $id)->where('book_id', $bookId)->first(),
            'storyline' => Storyline::query()->where('id', $id)->where('book_id', $bookId)->first(),
            'plot_point' => PlotPoint::query()->where('id', $id)->where('book_id', $bookId)->first(),
            'act' => Act::query()->where('id', $id)->where('book_id', $bookId)->first(),
            'chapter' => Chapter::query()->where('id', $id)->where('book_id', $bookId)->first(),
            'beat' => Beat::query()
                ->join('plot_points', 'plot_points.id', '=', 'beats.plot_point_id')
                ->where('beats.id', $id)
                ->where('plot_points.book_id', $bookId)
                ->select('beats.*')
                ->first(),
        };

        if (! $model) {
            throw new InvalidArgumentException("delete: {$target}.id {$id} does not belong to this book.");
        }

        $model->delete();

        return ['type' => 'delete', 'target' => $target, 'id' => $id, 'deleted' => true];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int}
     */
    private function writeAct(PlotCoachSession $session, array $data): array
    {
        if (isset($data['id'])) {
            return $this->updateAct($session, $data);
        }

        $this->requireString($data, 'title', 'act');

        $nextSortOrder = (int) (Act::query()
            ->where('book_id', $session->book_id)
            ->max('sort_order') ?? -1) + 1;

        $nextNumber = (int) (Act::query()
            ->where('book_id', $session->book_id)
            ->max('number') ?? 0) + 1;

        $act = Act::query()->create([
            'book_id' => $session->book_id,
            'number' => isset($data['number']) && is_numeric($data['number'])
                ? (int) $data['number']
                : $nextNumber,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? null,
            'sort_order' => isset($data['sort_order']) && is_numeric($data['sort_order'])
                ? (int) $data['sort_order']
                : $nextSortOrder,
        ]);

        return ['type' => 'act', 'id' => $act->id];
    }

    /**
     * Update an existing act — title, description, number, color, sort_order.
     * Captures previous values so undo restores the pre-update state.
     *
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int, updated: true, previous: array<string, mixed>}
     */
    private function updateAct(PlotCoachSession $session, array $data): array
    {
        $id = (int) $data['id'];

        $act = Act::query()
            ->where('id', $id)
            ->where('book_id', $session->book_id)
            ->first();

        if (! $act) {
            throw new InvalidArgumentException("act.id {$id} does not belong to this book.");
        }

        $patch = [];
        $previous = [];

        foreach (['title', 'description', 'color'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $previous[$field] = $act->getRawOriginal($field);
            $patch[$field] = $data[$field];
        }

        foreach (['number', 'sort_order'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $previous[$field] = $act->getRawOriginal($field);
            $patch[$field] = $this->coerceInt($data, $field, "act.{$field}");
        }

        if ($patch === []) {
            throw new InvalidArgumentException('act update requires at least one of: title, description, number, color, sort_order.');
        }

        $act->update($patch);

        return ['type' => 'act', 'id' => $act->id, 'updated' => true, 'previous' => $previous];
    }

    /**
     * Patch a narrow whitelist of session-level fields. Captures previous
     * values so undo can restore. Used by the agent to transition stage
     * (intake → plotting → refinement) and to lock the chosen coaching mode.
     *
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int, previous: array<string, mixed>}
     */
    private function writeSessionUpdate(PlotCoachSession $session, array $data): array
    {
        $allowed = ['stage', 'coaching_mode'];

        $patch = [];
        $previous = [];

        foreach ($allowed as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $patch[$field] = match ($field) {
                'stage' => $this->coerceStage($data['stage']),
                'coaching_mode' => $this->coerceCoachingMode($data['coaching_mode']),
            };

            $previous[$field] = $session->getRawOriginal($field);
        }

        if ($patch === []) {
            throw new InvalidArgumentException('session_update requires at least one of: stage, coaching_mode.');
        }

        $session->update($patch);

        return ['type' => 'session_update', 'id' => $session->id, 'previous' => $previous];
    }

    private function coerceStage(mixed $raw): string
    {
        if (! is_string($raw) || $raw === '') {
            throw new InvalidArgumentException('session_update.stage must be a non-empty string.');
        }

        $stage = PlotCoachStage::tryFrom($raw);

        if (! $stage) {
            throw new InvalidArgumentException('session_update.stage is not a known stage: '.$raw);
        }

        return $stage->value;
    }

    private function coerceCoachingMode(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_string($raw)) {
            throw new InvalidArgumentException('session_update.coaching_mode must be a string or null.');
        }

        $mode = CoachingMode::tryFrom($raw);

        if (! $mode) {
            throw new InvalidArgumentException('session_update.coaching_mode is not a known mode: '.$raw);
        }

        return $mode->value;
    }

    /**
     * Patch a narrow whitelist of intake-stage fields on the session's book.
     * Captures previous values so undo can restore rather than delete.
     *
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int, previous: array<string, mixed>}
     */
    private function writeBookUpdate(PlotCoachSession $session, array $data): array
    {
        $book = $session->book;

        if (! $book) {
            throw new InvalidArgumentException('book_update: session has no associated book.');
        }

        $allowed = ['premise', 'target_word_count', 'genre'];

        $patch = [];
        $previous = [];

        foreach ($allowed as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $patch[$field] = match ($field) {
                'premise' => $this->coercePremise($data['premise']),
                'target_word_count' => $this->coerceTargetWordCount($data['target_word_count']),
                'genre' => $this->coerceGenre($data['genre']),
            };

            $previous[$field] = $book->getRawOriginal($field);
        }

        if ($patch === []) {
            throw new InvalidArgumentException('book_update requires at least one of: premise, target_word_count, genre.');
        }

        $book->update($patch);

        return ['type' => 'book_update', 'id' => $book->id, 'previous' => $previous];
    }

    private function coercePremise(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (! is_string($raw)) {
            throw new InvalidArgumentException('book_update.premise must be a string or null.');
        }

        $trimmed = trim($raw);

        return $trimmed === '' ? null : $trimmed;
    }

    private function coerceTargetWordCount(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_numeric($raw)) {
            throw new InvalidArgumentException('book_update.target_word_count must be numeric or null.');
        }

        $value = (int) $raw;

        if ($value < 0) {
            throw new InvalidArgumentException('book_update.target_word_count must be non-negative.');
        }

        return $value;
    }

    private function coerceGenre(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_string($raw)) {
            throw new InvalidArgumentException('book_update.genre must be a string or null.');
        }

        $genre = Genre::tryFrom($raw);

        if (! $genre) {
            throw new InvalidArgumentException('book_update.genre is not a known genre: '.$raw);
        }

        return $genre->value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int}
     */
    private function writeCharacter(PlotCoachSession $session, array $data): array
    {
        if (isset($data['id'])) {
            return $this->updateCharacter($session, $data);
        }

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
     * @return array{type: string, id: int, updated: true, previous: array<string, mixed>}
     */
    private function updateCharacter(PlotCoachSession $session, array $data): array
    {
        $id = (int) $data['id'];

        $character = Character::query()
            ->where('id', $id)
            ->where('book_id', $session->book_id)
            ->first();

        if (! $character) {
            throw new InvalidArgumentException("character.id {$id} does not belong to this book.");
        }

        $patch = [];
        $previous = [];

        foreach (['name', 'ai_description', 'description'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $previous[$field] = $character->getRawOriginal($field);
            $patch[$field] = $data[$field];
        }

        if ($patch === []) {
            throw new InvalidArgumentException('character update requires at least one of: name, ai_description, description.');
        }

        $character->update($patch);

        return ['type' => 'character', 'id' => $character->id, 'updated' => true, 'previous' => $previous];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int}
     */
    private function writeWikiEntry(PlotCoachSession $session, array $data): array
    {
        if (isset($data['id'])) {
            return $this->updateWikiEntry($session, $data);
        }

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
     * @return array{type: string, id: int, updated: true, previous: array<string, mixed>}
     */
    private function updateWikiEntry(PlotCoachSession $session, array $data): array
    {
        $id = (int) $data['id'];

        $entry = WikiEntry::query()
            ->where('id', $id)
            ->where('book_id', $session->book_id)
            ->first();

        if (! $entry) {
            throw new InvalidArgumentException("wiki_entry.id {$id} does not belong to this book.");
        }

        $patch = [];
        $previous = [];

        foreach (['name', 'kind', 'ai_description', 'description'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $previous[$field] = $entry->getRawOriginal($field);

            $patch[$field] = $field === 'kind'
                ? $this->enum(WikiEntryKind::class, $data['kind'], 'wiki_entry.kind')->value
                : $data[$field];
        }

        if ($patch === []) {
            throw new InvalidArgumentException('wiki_entry update requires at least one of: name, kind, ai_description, description.');
        }

        $entry->update($patch);

        return ['type' => 'wiki_entry', 'id' => $entry->id, 'updated' => true, 'previous' => $previous];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int}
     */
    private function writeStoryline(PlotCoachSession $session, array $data): array
    {
        if (isset($data['id'])) {
            return $this->updateStoryline($session, $data);
        }

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
     * Update an existing storyline — name, type, color, sort_order. Captures
     * previous values so undo restores the pre-update state.
     *
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int, updated: true, previous: array<string, mixed>}
     */
    private function updateStoryline(PlotCoachSession $session, array $data): array
    {
        $id = (int) $data['id'];

        $storyline = Storyline::query()
            ->where('id', $id)
            ->where('book_id', $session->book_id)
            ->first();

        if (! $storyline) {
            throw new InvalidArgumentException("storyline.id {$id} does not belong to this book.");
        }

        $patch = [];
        $previous = [];

        foreach (['name', 'color'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $previous[$field] = $storyline->getRawOriginal($field);
            $patch[$field] = $data[$field];
        }

        if (array_key_exists('type', $data)) {
            $previous['type'] = $storyline->getRawOriginal('type');
            $patch['type'] = $this->enum(StorylineType::class, $data['type'], 'storyline.type')->value;
        }

        if (array_key_exists('sort_order', $data)) {
            $previous['sort_order'] = $storyline->getRawOriginal('sort_order');
            $patch['sort_order'] = $this->coerceInt($data, 'sort_order', 'storyline.sort_order');
        }

        if ($patch === []) {
            throw new InvalidArgumentException('storyline update requires at least one of: name, type, color, sort_order.');
        }

        $storyline->update($patch);

        return ['type' => 'storyline', 'id' => $storyline->id, 'updated' => true, 'previous' => $previous];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int}
     */
    private function writePlotPoint(PlotCoachSession $session, array $data): array
    {
        if (isset($data['id'])) {
            return $this->updatePlotPoint($session, $data);
        }

        $this->requireString($data, 'title', 'plot_point');

        $actId = $this->resolvePlotPointActId($session->book_id, $data);

        $type = isset($data['type'])
            ? $this->enum(PlotPointType::class, $data['type'], 'plot_point.type')
            : null;

        $status = isset($data['status'])
            ? $this->enum(PlotPointStatus::class, $data['status'], 'plot_point.status')
            : PlotPointStatus::Planned;

        $plotPoint = PlotPoint::query()->create([
            'book_id' => $session->book_id,
            'act_id' => $actId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $type?->value,
            'status' => $status->value,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        if (! empty($data['character_ids']) && is_array($data['character_ids'])) {
            $characterIds = $this->validateCharacterIds($session->book_id, $data['character_ids']);
            if ($characterIds) {
                $plotPoint->characters()->attach($characterIds);
            }
        }

        return ['type' => 'plot_point', 'id' => $plotPoint->id];
    }

    /**
     * Update an existing plot_point — title, description, type, status,
     * sort_order, `act_id` (or `act_number`) for rehanging onto a different
     * act, and `character_ids` to fully replace the attached characters.
     * Captures previous values (and previous character pivot ids) so undo
     * restores the pre-update state.
     *
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int, updated: true, previous: array<string, mixed>}
     */
    private function updatePlotPoint(PlotCoachSession $session, array $data): array
    {
        $id = (int) $data['id'];

        $plotPoint = PlotPoint::query()
            ->where('id', $id)
            ->where('book_id', $session->book_id)
            ->first();

        if (! $plotPoint) {
            throw new InvalidArgumentException("plot_point.id {$id} does not belong to this book.");
        }

        $patch = [];
        $previous = [];

        foreach (['title', 'description'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $previous[$field] = $plotPoint->getRawOriginal($field);
            $patch[$field] = $data[$field];
        }

        if (array_key_exists('sort_order', $data)) {
            $previous['sort_order'] = $plotPoint->getRawOriginal('sort_order');
            $patch['sort_order'] = $this->coerceInt($data, 'sort_order', 'plot_point.sort_order');
        }

        if (array_key_exists('type', $data)) {
            $previous['type'] = $plotPoint->getRawOriginal('type');
            $patch['type'] = $this->enum(PlotPointType::class, $data['type'], 'plot_point.type')->value;
        }

        if (array_key_exists('status', $data)) {
            $previous['status'] = $plotPoint->getRawOriginal('status');
            $patch['status'] = $this->enum(PlotPointStatus::class, $data['status'], 'plot_point.status')->value;
        }

        if (! empty($data['act_id']) || ! empty($data['act_number'])) {
            $newActId = $this->resolvePlotPointActId($session->book_id, $data);
            if ($newActId !== (int) $plotPoint->act_id) {
                $previous['act_id'] = $plotPoint->getRawOriginal('act_id');
                $patch['act_id'] = $newActId;
            }
        }

        $syncCharacters = false;
        $newCharacterIds = [];

        if (array_key_exists('character_ids', $data)) {
            if (! is_array($data['character_ids'])) {
                throw new InvalidArgumentException('plot_point.character_ids must be an array.');
            }
            $newCharacterIds = $this->validateCharacterIds($session->book_id, $data['character_ids']);
            $previous['character_ids'] = $plotPoint->characters()
                ->pluck('characters.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
            $syncCharacters = true;
        }

        if ($patch === [] && ! $syncCharacters) {
            throw new InvalidArgumentException('plot_point update requires at least one of: title, description, type, status, sort_order, act_id, act_number, character_ids.');
        }

        if ($patch !== []) {
            $plotPoint->update($patch);
        }

        if ($syncCharacters) {
            $plotPoint->characters()->sync($newCharacterIds);
        }

        return ['type' => 'plot_point', 'id' => $plotPoint->id, 'updated' => true, 'previous' => $previous];
    }

    /**
     * @param  array<int, int|string>  $raw
     * @return list<int>
     */
    private function validateCharacterIds(int $bookId, array $raw): array
    {
        $ids = array_values(array_unique(array_map('intval', $raw)));

        if ($ids === []) {
            return [];
        }

        $valid = Character::query()
            ->whereIn('id', $ids)
            ->where('book_id', $bookId)
            ->pluck('id')
            ->all();

        $missing = array_diff($ids, $valid);

        if ($missing) {
            throw new InvalidArgumentException(
                'plot_point.character_ids references characters that do not belong to this book: '.implode(', ', $missing),
            );
        }

        return array_map('intval', $valid);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int}
     */
    private function writeBeat(PlotCoachSession $session, array $data): array
    {
        if (isset($data['id'])) {
            return $this->updateBeat($session, $data);
        }

        $this->requireString($data, 'title', 'beat');

        $plotPointId = $this->resolveBeatPlotPointId($session->book_id, $data);

        $status = isset($data['status'])
            ? $this->enum(BeatStatus::class, $data['status'], 'beat.status')
            : BeatStatus::Planned;

        $beat = Beat::query()->create([
            'plot_point_id' => $plotPointId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $status->value,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return ['type' => 'beat', 'id' => $beat->id];
    }

    /**
     * Update an existing beat — title, description, status, sort_order, and
     * crucially `plot_point_id` (or `plot_point_title`) for rehanging onto a
     * different plot point. Captures previous values so undo restores the
     * pre-update state.
     *
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int, updated: true, previous: array<string, mixed>}
     */
    private function updateBeat(PlotCoachSession $session, array $data): array
    {
        $id = (int) $data['id'];

        $beat = Beat::query()
            ->join('plot_points', 'plot_points.id', '=', 'beats.plot_point_id')
            ->where('beats.id', $id)
            ->where('plot_points.book_id', $session->book_id)
            ->select('beats.*')
            ->first();

        if (! $beat) {
            throw new InvalidArgumentException("beat.id {$id} does not belong to this book.");
        }

        $patch = [];
        $previous = [];

        foreach (['title', 'description'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $previous[$field] = $beat->getRawOriginal($field);
            $patch[$field] = $data[$field];
        }

        if (array_key_exists('sort_order', $data)) {
            $previous['sort_order'] = $beat->getRawOriginal('sort_order');
            $patch['sort_order'] = (int) $data['sort_order'];
        }

        if (array_key_exists('status', $data)) {
            $previous['status'] = $beat->getRawOriginal('status');
            $patch['status'] = $this->enum(BeatStatus::class, $data['status'], 'beat.status')->value;
        }

        if (! empty($data['plot_point_id']) || ! empty($data['plot_point_title'])) {
            $newPlotPointId = $this->resolveBeatPlotPointId($session->book_id, $data);
            if ($newPlotPointId !== (int) $beat->plot_point_id) {
                $previous['plot_point_id'] = $beat->getRawOriginal('plot_point_id');
                $patch['plot_point_id'] = $newPlotPointId;
            }
        }

        if ($patch === []) {
            throw new InvalidArgumentException('beat update requires at least one of: title, description, status, sort_order, plot_point_id, plot_point_title.');
        }

        $beat->update($patch);

        return ['type' => 'beat', 'id' => $beat->id, 'updated' => true, 'previous' => $previous];
    }

    /**
     * Resolve a beat's plot_point from either an explicit id or a fallback
     * `plot_point_title`. The title fallback is what lets the AI propose
     * `plot_point + beat` pairs in a single batch — the plot_point is
     * created first in the same transaction and the title lookup finds it.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveBeatPlotPointId(int $bookId, array $data): int
    {
        if (! empty($data['plot_point_id'])) {
            $id = (int) $data['plot_point_id'];

            $belongs = PlotPoint::query()
                ->where('id', $id)
                ->where('book_id', $bookId)
                ->exists();

            if (! $belongs) {
                throw new InvalidArgumentException(
                    "beat.plot_point_id {$id} does not belong to this book.",
                );
            }

            return $id;
        }

        if (! empty($data['plot_point_title']) && is_string($data['plot_point_title'])) {
            $title = trim($data['plot_point_title']);

            $id = PlotPoint::query()
                ->where('book_id', $bookId)
                ->where('title', $title)
                ->orderByDesc('id')
                ->value('id');

            if (! $id) {
                throw new InvalidArgumentException(
                    "beat.plot_point_title \"{$title}\" does not match any plot point in this book. Propose the plot point first (or earlier in this same batch).",
                );
            }

            return (int) $id;
        }

        throw new InvalidArgumentException('beat requires plot_point_id or plot_point_title.');
    }

    /**
     * Idempotent chapter stub. Reuses an existing (book, storyline, title)
     * match — beats get synced, metadata is never renamed or overwritten.
     *
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int, reused?: bool}
     */
    private function writeChapter(PlotCoachSession $session, array $data): array
    {
        if (isset($data['id'])) {
            return $this->updateChapter($session, $data);
        }

        $this->requireString($data, 'title', 'chapter');

        if (empty($data['storyline_id'])) {
            throw new InvalidArgumentException('chapter.storyline_id is required.');
        }

        $storyline = Storyline::query()
            ->where('id', (int) $data['storyline_id'])
            ->where('book_id', $session->book_id)
            ->first();

        if (! $storyline) {
            throw new InvalidArgumentException('chapter.storyline_id does not belong to this book.');
        }

        $beatIds = $this->validateBeatIds($session->book_id, $data['beat_ids'] ?? []);
        $povCharacterId = $this->validatePovCharacterId($session->book_id, $data['pov_character_id'] ?? null);
        $actId = $this->validateActId($session->book_id, $data['act_id'] ?? null);

        $existing = Chapter::query()
            ->where('book_id', $session->book_id)
            ->where('storyline_id', $storyline->id)
            ->where('title', $data['title'])
            ->first();

        if ($existing) {
            if ($beatIds) {
                $existing->beats()->syncWithoutDetaching($beatIds);
            }

            return ['type' => 'chapter', 'id' => $existing->id, 'reused' => true];
        }

        $nextOrder = (int) (Chapter::query()
            ->where('book_id', $session->book_id)
            ->max('reader_order') ?? -1) + 1;

        $seedContent = $this->seedContentForStub($session, $beatIds);
        $seedWords = $seedContent === '' ? 0 : str_word_count(strip_tags($seedContent));

        $chapter = Chapter::query()->create([
            'book_id' => $session->book_id,
            'storyline_id' => $storyline->id,
            'act_id' => $actId,
            'pov_character_id' => $povCharacterId,
            'title' => $data['title'],
            'reader_order' => $nextOrder,
            'status' => ChapterStatus::Draft,
            'word_count' => $seedWords,
        ]);

        $chapter->versions()->create([
            'version_number' => 1,
            'content' => $seedContent,
            'source' => VersionSource::Original,
            'is_current' => true,
        ]);

        $chapter->scenes()->create([
            'title' => 'Scene 1',
            'content' => $seedContent,
            'sort_order' => 0,
            'word_count' => $seedWords,
        ]);

        if ($beatIds) {
            $chapter->beats()->attach($beatIds);
        }

        return ['type' => 'chapter', 'id' => $chapter->id];
    }

    /**
     * Update an existing chapter — title, storyline_id (move between
     * storylines), pov_character_id, act_id, reader_order, and `beat_ids`
     * for a full pivot resync. Captures previous field values plus the
     * pre-update beat pivot so undo restores the chapter exactly.
     *
     * Word counts and version content are intentionally NOT touched here:
     * those track the manuscript text, not the structural metadata the
     * coach is editing.
     *
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: int, updated: true, previous: array<string, mixed>}
     */
    private function updateChapter(PlotCoachSession $session, array $data): array
    {
        $id = (int) $data['id'];

        $chapter = Chapter::query()
            ->where('id', $id)
            ->where('book_id', $session->book_id)
            ->first();

        if (! $chapter) {
            throw new InvalidArgumentException("chapter.id {$id} does not belong to this book.");
        }

        $patch = [];
        $previous = [];

        if (array_key_exists('title', $data)) {
            if (! is_string($data['title']) || trim($data['title']) === '') {
                throw new InvalidArgumentException('chapter.title must be a non-empty string.');
            }
            $previous['title'] = $chapter->getRawOriginal('title');
            $patch['title'] = $data['title'];
        }

        if (array_key_exists('storyline_id', $data)) {
            $newStorylineId = $this->validateStorylineId($session->book_id, $data['storyline_id']);
            if ($newStorylineId !== (int) $chapter->storyline_id) {
                $previous['storyline_id'] = $chapter->getRawOriginal('storyline_id');
                $patch['storyline_id'] = $newStorylineId;
            }
        }

        if (array_key_exists('pov_character_id', $data)) {
            $povId = $this->validatePovCharacterId($session->book_id, $data['pov_character_id']);
            if ($povId !== $chapter->pov_character_id) {
                $previous['pov_character_id'] = $chapter->getRawOriginal('pov_character_id');
                $patch['pov_character_id'] = $povId;
            }
        }

        if (array_key_exists('act_id', $data)) {
            $actId = $this->validateActId($session->book_id, $data['act_id']);
            if ($actId !== $chapter->act_id) {
                $previous['act_id'] = $chapter->getRawOriginal('act_id');
                $patch['act_id'] = $actId;
            }
        }

        if (array_key_exists('reader_order', $data)) {
            $previous['reader_order'] = $chapter->getRawOriginal('reader_order');
            $patch['reader_order'] = $this->coerceInt($data, 'reader_order', 'chapter.reader_order');
        }

        $syncBeats = false;
        $newBeatIds = [];

        if (array_key_exists('beat_ids', $data)) {
            if (! is_array($data['beat_ids'])) {
                throw new InvalidArgumentException('chapter.beat_ids must be an array.');
            }
            $newBeatIds = $this->validateBeatIds($session->book_id, $data['beat_ids']);
            $previous['beat_ids'] = $chapter->beats()
                ->pluck('beats.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
            $syncBeats = true;
        }

        if ($patch === [] && ! $syncBeats) {
            throw new InvalidArgumentException('chapter update requires at least one of: title, storyline_id, pov_character_id, act_id, reader_order, beat_ids.');
        }

        if ($patch !== []) {
            $chapter->update($patch);
        }

        if ($syncBeats) {
            $chapter->beats()->sync($newBeatIds);
        }

        return ['type' => 'chapter', 'id' => $chapter->id, 'updated' => true, 'previous' => $previous];
    }

    /**
     * @param  array<int, int|string>|mixed  $raw
     * @return list<int>
     */
    private function validateBeatIds(int $bookId, mixed $raw): array
    {
        if (! is_array($raw) || empty($raw)) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $raw)));

        $valid = Beat::query()
            ->whereIn('beats.id', $ids)
            ->join('plot_points', 'plot_points.id', '=', 'beats.plot_point_id')
            ->where('plot_points.book_id', $bookId)
            ->pluck('beats.id')
            ->all();

        $missing = array_diff($ids, $valid);

        if ($missing) {
            throw new InvalidArgumentException(
                'chapter.beat_ids references beats that do not belong to this book: '.implode(', ', $missing)
            );
        }

        return array_map('intval', $valid);
    }

    private function validatePovCharacterId(int $bookId, mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $id = (int) $raw;

        $exists = Character::query()
            ->where('id', $id)
            ->where('book_id', $bookId)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException('chapter.pov_character_id does not belong to this book.');
        }

        return $id;
    }

    /**
     * Resolve a plot_point's act from either an explicit `act_id` or a fallback
     * `act_number`. Validates the resolved act belongs to the session's book
     * so a slip in the AI's bookkeeping can't create orphan plot points.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolvePlotPointActId(int $bookId, array $data): int
    {
        if (! empty($data['act_id'])) {
            $id = (int) $data['act_id'];

            $exists = Act::query()
                ->where('id', $id)
                ->where('book_id', $bookId)
                ->exists();

            if (! $exists) {
                throw new InvalidArgumentException(
                    "plot_point.act_id {$id} does not belong to this book. Use an act_id from the current state block (or pass act_number instead).",
                );
            }

            return $id;
        }

        if (! empty($data['act_number'])) {
            $num = (int) $data['act_number'];

            $id = Act::query()
                ->where('book_id', $bookId)
                ->where('number', $num)
                ->value('id');

            if (! $id) {
                throw new InvalidArgumentException(
                    "plot_point.act_number {$num} does not match any act in this book.",
                );
            }

            return (int) $id;
        }

        throw new InvalidArgumentException('plot_point requires act_id or act_number.');
    }

    private function validateActId(int $bookId, mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $id = (int) $raw;

        $exists = DB::table('acts')
            ->where('id', $id)
            ->where('book_id', $bookId)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException('chapter.act_id does not belong to this book.');
        }

        return $id;
    }

    private function validateStorylineId(int $bookId, mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            throw new InvalidArgumentException('chapter.storyline_id is required.');
        }

        $id = (int) $raw;

        $exists = Storyline::query()
            ->where('id', $id)
            ->where('book_id', $bookId)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException("chapter.storyline_id {$id} does not belong to this book.");
        }

        return $id;
    }

    /**
     * Coerce a write field to int, throwing a typed error if non-numeric.
     * Used by every updater that accepts integer column patches so the
     * "must be numeric" message is uniform and `is_numeric` checks aren't
     * scattered across the file.
     *
     * @param  array<string, mixed>  $data
     */
    private function coerceInt(array $data, string $key, string $field): int
    {
        if (! is_numeric($data[$key])) {
            throw new InvalidArgumentException("{$field} must be numeric.");
        }

        return (int) $data[$key];
    }

    /**
     * Produce the initial scene body when the book opts into intent-seeded stubs.
     *
     * @param  list<int>  $beatIds
     */
    private function seedContentForStub(PlotCoachSession $session, array $beatIds): string
    {
        $seed = (bool) $session->book?->plot_coach_seed_stub_with_intent;

        if (! $seed || empty($beatIds)) {
            return '';
        }

        $beats = Beat::query()
            ->whereIn('id', $beatIds)
            ->orderBy('sort_order')
            ->get(['title', 'description']);

        if ($beats->isEmpty()) {
            return '';
        }

        $lines = $beats->map(function (Beat $beat): string {
            $title = e((string) $beat->title);
            $description = trim((string) ($beat->description ?? ''));

            return $description !== ''
                ? "<p><strong>Beat:</strong> {$title} — ".e($description).'</p>'
                : "<p><strong>Beat:</strong> {$title}</p>";
        })->implode('');

        return $lines;
    }

    /**
     * Revert a single persisted record from a batch payload. Creates are
     * deleted; book_update restores previously-captured values. Missing rows
     * are skipped silently (user may have deleted them manually).
     *
     * @param  array{type?: string, id?: int, reused?: bool, previous?: array<string, mixed>}  $write
     */
    private function deleteWrite(array $write): void
    {
        if (! isset($write['type']) || ! isset($write['id'])) {
            return;
        }

        if ($write['type'] === 'book_update') {
            $this->revertBookUpdate($write);

            return;
        }

        if ($write['type'] === 'session_update') {
            $this->revertSessionUpdate($write);

            return;
        }

        if ($write['type'] === 'delete') {
            $this->revertDelete($write);

            return;
        }

        // Chapters marked as reused existed before the batch — never delete
        // them on undo, that would destroy user-created work.
        if ($write['type'] === 'chapter' && ! empty($write['reused'])) {
            return;
        }

        $model = match ($write['type']) {
            'character' => Character::query()->find($write['id']),
            'wiki_entry' => WikiEntry::query()->find($write['id']),
            'storyline' => Storyline::query()->find($write['id']),
            'plot_point' => PlotPoint::query()->find($write['id']),
            'beat' => Beat::query()->find($write['id']),
            'chapter' => Chapter::query()->find($write['id']),
            'act' => Act::query()->find($write['id']),
            default => null,
        };

        if (! $model) {
            return;
        }

        // Updates capture previous field values; restoring them is the undo.
        // Without this guard the row would be deleted, destroying pre-batch
        // user work.
        if (! empty($write['updated'])) {
            $previous = $write['previous'] ?? [];

            if (! is_array($previous) || $previous === []) {
                return;
            }

            $pivotCharacterIds = null;
            $pivotBeatIds = null;

            if ($write['type'] === 'plot_point' && array_key_exists('character_ids', $previous)) {
                $pivotCharacterIds = is_array($previous['character_ids']) ? $previous['character_ids'] : [];
                unset($previous['character_ids']);
            }

            if ($write['type'] === 'chapter' && array_key_exists('beat_ids', $previous)) {
                $pivotBeatIds = is_array($previous['beat_ids']) ? $previous['beat_ids'] : [];
                unset($previous['beat_ids']);
            }

            if ($previous !== []) {
                $model->update($previous);
            }

            if ($pivotCharacterIds !== null && $model instanceof PlotPoint) {
                $model->characters()->sync($pivotCharacterIds);
            }

            if ($pivotBeatIds !== null && $model instanceof Chapter) {
                $model->beats()->sync($pivotBeatIds);
            }

            return;
        }

        $model->delete();
    }

    /**
     * @param  array{id?: int, previous?: array<string, mixed>}  $write
     */
    private function revertBookUpdate(array $write): void
    {
        $previous = $write['previous'] ?? [];

        if (! is_array($previous) || $previous === []) {
            return;
        }

        $book = Book::query()->find($write['id']);

        if (! $book) {
            return;
        }

        $book->update($previous);
    }

    /**
     * Restore a soft-deleted entity. Missing rows (force-deleted or never
     * existed) are skipped silently — undo stays best-effort.
     *
     * @param  array{type?: string, target?: string, id?: int}  $write
     */
    private function revertDelete(array $write): void
    {
        $target = $write['target'] ?? '';
        $id = $write['id'] ?? null;

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return;
        }

        $id = (int) $id;

        $model = match ($target) {
            'character' => Character::withTrashed()->find($id),
            'wiki_entry' => WikiEntry::withTrashed()->find($id),
            'storyline' => Storyline::withTrashed()->find($id),
            'plot_point' => PlotPoint::withTrashed()->find($id),
            'beat' => Beat::withTrashed()->find($id),
            'chapter' => Chapter::withTrashed()->find($id),
            'act' => Act::withTrashed()->find($id),
            default => null,
        };

        if ($model && method_exists($model, 'restore')) {
            $model->restore();
        }
    }

    /**
     * @param  array{id?: int, previous?: array<string, mixed>}  $write
     */
    private function revertSessionUpdate(array $write): void
    {
        $previous = $write['previous'] ?? [];

        if (! is_array($previous) || $previous === []) {
            return;
        }

        $session = PlotCoachSession::query()->find($write['id']);

        if (! $session) {
            return;
        }

        $session->update($previous);
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
