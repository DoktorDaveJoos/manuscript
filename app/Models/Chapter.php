<?php

namespace App\Models;

use App\Enums\ChapterStatus;
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
     * @return HasMany<Scene, $this>
     */
    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class)->orderBy('sort_order');
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

    public function getFullContent(): string
    {
        return $this->scenes->pluck('content')->filter()->implode("\n");
    }

    public function recalculateWordCount(): void
    {
        $this->update([
            'word_count' => $this->scenes()->sum('word_count'),
        ]);
    }
}
