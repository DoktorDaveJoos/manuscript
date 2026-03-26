<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EditorialReview extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'progress' => 'array',
            'overall_score' => 'integer',
            'top_strengths' => 'array',
            'top_improvements' => 'array',
            'resolved_findings' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return HasMany<EditorialReviewSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(EditorialReviewSection::class);
    }

    /**
     * @return HasMany<EditorialReviewChapterNote, $this>
     */
    public function chapterNotes(): HasMany
    {
        return $this->hasMany(EditorialReviewChapterNote::class);
    }
}
