<?php

namespace App\Models;

use App\Enums\ChapterStatus;
use App\Enums\VersionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chapter extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChapterStatus::class,
            'reader_order' => 'integer',
            'word_count' => 'integer',
            'tension_score' => 'integer',
            'hook_score' => 'integer',
            'emotional_shift_magnitude' => 'integer',
            'micro_tension_score' => 'integer',
            'entry_hook_score' => 'integer',
            'exit_hook_score' => 'integer',
            'sensory_grounding' => 'integer',
            'analyzed_at' => 'datetime',
            'ai_prepared_at' => 'datetime',
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
     * @return BelongsTo<Storyline, $this>
     */
    public function storyline(): BelongsTo
    {
        return $this->belongsTo(Storyline::class);
    }

    /**
     * @return BelongsTo<Act, $this>
     */
    public function act(): BelongsTo
    {
        return $this->belongsTo(Act::class);
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function povCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'pov_character_id');
    }

    /**
     * @return HasMany<ChapterVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ChapterVersion::class);
    }

    /**
     * @return HasOne<ChapterVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(ChapterVersion::class)->where('is_current', true);
    }

    /**
     * @return HasOne<ChapterVersion, $this>
     */
    public function pendingVersion(): HasOne
    {
        return $this->hasOne(ChapterVersion::class)->where('status', VersionStatus::Pending);
    }

    /**
     * @return HasMany<Scene, $this>
     */
    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<Analysis, $this>
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    /**
     * @return BelongsToMany<Character, $this>
     */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_chapter')
            ->withPivot(['role', 'notes'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<WikiEntry, $this>
     */
    public function wikiEntries(): BelongsToMany
    {
        return $this->belongsToMany(WikiEntry::class, 'wiki_entry_chapter')
            ->withPivot(['notes'])
            ->withTimestamps();
    }

    public function refreshContentHash(): void
    {
        $this->load('scenes');
        $content = $this->getFullContent();
        $this->updateQuietly([
            'content_hash' => $content !== '' ? hash('xxh128', $content) : null,
        ]);
    }

    public function needsAiPreparation(): bool
    {
        if ($this->content_hash === null && $this->prepared_content_hash === null) {
            return false;
        }

        return $this->content_hash !== $this->prepared_content_hash;
    }

    /**
     * @param  Builder<Chapter>  $query
     */
    public function scopeNeedsAiPreparation(Builder $query): void
    {
        $query->whereNotNull('content_hash')
            ->where(function (Builder $q) {
                $q->whereColumn('content_hash', '!=', 'prepared_content_hash')
                    ->orWhereNull('prepared_content_hash');
            });
    }

    public function getFullContent(): string
    {
        return $this->scenes->pluck('content')->filter()->implode("\n");
    }

    public function getContentWithSceneBreaks(): string
    {
        return $this->scenes->pluck('content')->filter()->implode('<hr>');
    }

    public function recalculateWordCount(): void
    {
        $this->update([
            'word_count' => $this->scenes()->sum('word_count'),
        ]);

        $this->refreshContentHash();
    }

    public function replaceScenesWithContent(?string $content): void
    {
        $this->scenes()->forceDelete();
        $wordCount = str_word_count(strip_tags($content ?? ''));
        $this->scenes()->create([
            'title' => 'Scene 1',
            'content' => $content,
            'sort_order' => 0,
            'word_count' => $wordCount,
        ]);
        $this->update(['word_count' => $wordCount]);

        $this->refreshContentHash();
    }

    /**
     * @param  array<int, array{title: string, sort_order: int}>|null  $sceneMap
     */
    public function replaceSceneContents(string $content, ?array $sceneMap): void
    {
        $segments = preg_split('/<hr\s*\/?>/', $content);
        $segments = array_values(array_filter($segments, fn ($s) => trim($s) !== ''));

        if (count($segments) <= 1) {
            $this->replaceScenesWithContent($content);

            return;
        }

        $existingScenes = $this->scenes()->orderBy('sort_order')->get();
        $totalWordCount = 0;

        foreach ($segments as $index => $segment) {
            $segment = trim($segment);
            $wordCount = str_word_count(strip_tags($segment));
            $totalWordCount += $wordCount;

            $title = $sceneMap[$index]['title'] ?? 'Scene '.($index + 1);

            if ($index < $existingScenes->count()) {
                $existingScenes[$index]->update([
                    'title' => $title,
                    'content' => $segment,
                    'sort_order' => $index,
                    'word_count' => $wordCount,
                ]);
            } else {
                $this->scenes()->create([
                    'title' => $title,
                    'content' => $segment,
                    'sort_order' => $index,
                    'word_count' => $wordCount,
                ]);
            }
        }

        // Delete excess scenes
        if (count($segments) < $existingScenes->count()) {
            $excessIds = $existingScenes->slice(count($segments))->pluck('id');
            $this->scenes()->whereIn('id', $excessIds)->forceDelete();
        }

        $this->update(['word_count' => $totalWordCount]);

        $this->refreshContentHash();
    }
}
