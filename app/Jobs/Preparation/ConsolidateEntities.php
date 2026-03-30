<?php

namespace App\Jobs\Preparation;

use App\Ai\Agents\EntityConsolidator;
use App\Enums\CharacterRole;
use App\Jobs\Concerns\DetectsTransientErrors;
use App\Models\AiPreparation;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\WikiEntry;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ConsolidateEntities implements ShouldQueue
{
    use Batchable, DetectsTransientErrors, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** @var list<int> */
    public array $backoff = [15];

    public int $timeout = 120;

    private const ROLE_PRIORITY = [
        CharacterRole::Protagonist->value => 3,
        CharacterRole::Supporting->value => 2,
        CharacterRole::Mentioned->value => 1,
    ];

    public function __construct(
        private Book $book,
        private AiPreparation $preparation,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $characters = $this->book->characters()->get();
        $wikiEntries = $this->book->wikiEntries()->get();

        if ($characters->count() + $wikiEntries->count() < 2) {
            $this->preparation->increment('current_phase_progress');

            return;
        }

        try {
            $prompt = $this->buildPrompt($characters, $wikiEntries);
            $agent = new EntityConsolidator($this->book);
            $response = $agent->prompt($prompt);
            $result = $response->toArray();

            $this->applyCharacterMerges($result['character_merges'] ?? []);
            $this->applyEntityMerges($result['entity_merges'] ?? []);

            $this->preparation->increment('current_phase_progress');
        } catch (Throwable $e) {
            if ($this->isTransient($e)) {
                throw $e;
            }

            $this->preparation->appendPhaseError('entity_extraction', null, 'Consolidation: '.$e->getMessage());
            $this->preparation->increment('current_phase_progress');
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->preparation->appendPhaseError('entity_extraction', null, 'Consolidation: '.$exception->getMessage());
        $this->preparation->increment('current_phase_progress');
    }

    /**
     * @param  EloquentCollection<int, Character>  $characters
     * @param  EloquentCollection<int, WikiEntry>  $wikiEntries
     */
    private function buildPrompt(EloquentCollection $characters, EloquentCollection $wikiEntries): string
    {
        $lines = ["Identify duplicate entries in this book's entity database.\n"];

        if ($characters->isNotEmpty()) {
            $lines[] = "## Characters\n";
            foreach ($characters as $character) {
                $aliases = ! empty($character->aliases) ? ' (aliases: '.implode(', ', $character->aliases).')' : '';
                $desc = $character->fullDescription() ?? 'No description';
                $source = $character->is_ai_extracted ? '' : ' [MANUAL]';
                $lines[] = "- ID:{$character->id} \"{$character->name}\"{$aliases}{$source} — {$desc}";
            }
            $lines[] = '';
        }

        if ($wikiEntries->isNotEmpty()) {
            $lines[] = "## World Entities\n";
            foreach ($wikiEntries as $entry) {
                $aliases = ! empty($entry->metadata['aliases']) ? ' (aliases: '.implode(', ', $entry->metadata['aliases']).')' : '';
                $desc = $entry->fullDescription() ?? 'No description';
                $source = $entry->is_ai_extracted ? '' : ' [MANUAL]';
                $lines[] = "- ID:{$entry->id} [{$entry->kind->value}] \"{$entry->name}\"{$aliases}{$source} — {$desc}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, array<string, mixed>>  $merges
     */
    private function applyCharacterMerges(array $merges): void
    {
        foreach ($merges as $merge) {
            if (empty($merge['canonical_id']) || empty($merge['duplicate_ids'])) {
                continue;
            }

            DB::transaction(function () use ($merge) {
                $canonical = $this->book->characters()->find($merge['canonical_id']);

                if (! $canonical) {
                    return;
                }

                $duplicates = $this->book->characters()
                    ->whereIn('id', $merge['duplicate_ids'])
                    ->get();

                if ($duplicates->isEmpty()) {
                    return;
                }

                $canonical->name = $merge['canonical_name'];
                $canonical->aliases = $this->mergeAliases(
                    $canonical->aliases ?? [],
                    $duplicates->pluck('aliases')->push($duplicates->pluck('name')->all()),
                    $merge['canonical_name'],
                );

                $this->keepLongestField($canonical, $duplicates, 'description');
                $this->keepLongestField($canonical, $duplicates, 'ai_description');
                $this->resolveEarliestAppearance($canonical, $duplicates);
                $canonical->save();

                // Batch-collect pivot syncs, then apply once per duplicate
                $canonicalPivots = $canonical->chapters()
                    ->withPivot(['role'])
                    ->get()
                    ->keyBy('id');

                foreach ($duplicates as $duplicate) {
                    $syncs = [];

                    foreach ($duplicate->chapters()->withPivot(['role'])->get() as $chapter) {
                        $newRole = $chapter->pivot->role ?? CharacterRole::Mentioned->value;
                        $existing = $canonicalPivots->get($chapter->id);

                        if ($existing) {
                            $newRole = $this->higherRole($existing->pivot->role ?? CharacterRole::Mentioned->value, $newRole);
                        }

                        $syncs[$chapter->id] = ['role' => $newRole];
                    }

                    if (! empty($syncs)) {
                        $canonical->chapters()->syncWithoutDetaching($syncs);
                        // Refresh canonical pivots for subsequent duplicates
                        $canonicalPivots = $canonical->chapters()
                            ->withPivot(['role'])
                            ->get()
                            ->keyBy('id');
                    }

                    $duplicate->chapters()->detach();
                    $duplicate->delete();
                }
            });
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $merges
     */
    private function applyEntityMerges(array $merges): void
    {
        foreach ($merges as $merge) {
            if (empty($merge['canonical_id']) || empty($merge['duplicate_ids'])) {
                continue;
            }

            DB::transaction(function () use ($merge) {
                $canonical = $this->book->wikiEntries()->find($merge['canonical_id']);

                if (! $canonical) {
                    return;
                }

                $duplicates = $this->book->wikiEntries()
                    ->whereIn('id', $merge['duplicate_ids'])
                    ->get();

                if ($duplicates->isEmpty()) {
                    return;
                }

                $canonical->name = $merge['canonical_name'];

                $metadata = $canonical->metadata ?? [];
                $metadata['aliases'] = $this->mergeAliases(
                    $metadata['aliases'] ?? [],
                    $duplicates->pluck('metadata')->map(fn ($m) => $m['aliases'] ?? [])->push($duplicates->pluck('name')->all()),
                    $merge['canonical_name'],
                );
                $canonical->metadata = $metadata;

                $this->keepLongestField($canonical, $duplicates, 'description');
                $this->keepLongestField($canonical, $duplicates, 'ai_description');
                $this->resolveEarliestAppearance($canonical, $duplicates);
                $canonical->save();

                foreach ($duplicates as $duplicate) {
                    $canonical->chapters()->syncWithoutDetaching(
                        $duplicate->chapters()->pluck('chapter_id')
                            ->mapWithKeys(fn ($id) => [$id => []])->all()
                    );

                    $duplicate->chapters()->detach();
                    $duplicate->delete();
                }
            });
        }
    }

    /**
     * Merge alias arrays from canonical and duplicates, deduplicating and excluding the canonical name.
     *
     * @param  array<int, string>  $canonicalAliases
     * @param  Collection<int, mixed>  $duplicateAliasGroups
     * @return list<string>
     */
    private function mergeAliases(array $canonicalAliases, $duplicateAliasGroups, string $canonicalName): array
    {
        $all = collect($canonicalAliases);

        foreach ($duplicateAliasGroups as $group) {
            $all = $all->merge(is_array($group) ? $group : [$group]);
        }

        return $all
            ->map(fn ($a) => trim($a))
            ->filter()
            ->unique()
            ->reject(fn ($a) => $a === $canonicalName)
            ->values()
            ->all();
    }

    /**
     * @param  EloquentCollection<int, Model>  $duplicates
     */
    private function keepLongestField(Model $canonical, EloquentCollection $duplicates, string $field): void
    {
        foreach ($duplicates as $duplicate) {
            if (mb_strlen($duplicate->{$field} ?? '') > mb_strlen($canonical->{$field} ?? '')) {
                $canonical->{$field} = $duplicate->{$field};
            }
        }
    }

    /**
     * @param  EloquentCollection<int, Model>  $duplicates
     */
    private function resolveEarliestAppearance(Model $canonical, EloquentCollection $duplicates): void
    {
        $allAppearanceIds = collect([$canonical->first_appearance])
            ->merge($duplicates->pluck('first_appearance'))
            ->filter()
            ->unique();

        if ($allAppearanceIds->isEmpty()) {
            return;
        }

        $earliest = Chapter::whereIn('id', $allAppearanceIds)
            ->orderBy('reader_order')
            ->first();

        if ($earliest) {
            $canonical->first_appearance = $earliest->id;
        }
    }

    private function higherRole(string $a, string $b): string
    {
        $priorityA = self::ROLE_PRIORITY[$a] ?? 0;
        $priorityB = self::ROLE_PRIORITY[$b] ?? 0;

        return $priorityA >= $priorityB ? $a : $b;
    }
}
