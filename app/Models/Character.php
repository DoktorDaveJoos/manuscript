<?php

namespace App\Models;

use App\Models\Concerns\HasDualDescription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Character extends Model
{
    use HasDualDescription, HasFactory, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'storylines' => 'array',
            'is_ai_extracted' => 'boolean',
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
     * @return BelongsTo<Chapter, $this>
     */
    public function firstAppearanceChapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'first_appearance');
    }

    /**
     * @return BelongsToMany<Chapter, $this>
     */
    public function chapters(): BelongsToMany
    {
        return $this->belongsToMany(Chapter::class, 'character_chapter')
            ->withPivot(['role', 'notes'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<PlotPoint, $this>
     */
    public function plotPoints(): BelongsToMany
    {
        return $this->belongsToMany(PlotPoint::class)
            ->withPivot('role')
            ->withTimestamps();
    }
}
