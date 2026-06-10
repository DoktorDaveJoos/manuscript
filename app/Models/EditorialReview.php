<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EditorialReview extends Model
{
    use HasFactory;

    /**
     * Minutes without a progress write after which a running review is
     * considered dead (1.5x the 30-minute finalize-job timeout).
     */
    public const STALE_AFTER_MINUTES = 45;

    protected $guarded = [];

    /**
     * Fail running reviews whose workers stopped writing progress, optionally
     * scoped to one book. Returns the number of reviews marked failed.
     */
    public static function failStale(?Book $book = null): int
    {
        return static::query()
            ->when($book, fn ($query) => $query->where('book_id', $book->id))
            ->whereIn('status', ['pending', 'analyzing', 'synthesizing'])
            ->where('updated_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->update([
                'status' => 'failed',
                'error_message' => __('Review timed out. Please try again.'),
                'updated_at' => now(),
            ]);
    }

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
            'is_pre_editorial' => 'boolean',
            'resolved_findings' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The review's chapter note for one chapter, or null when none exists.
     */
    public function chapterNoteFor(int $chapterId): ?string
    {
        return $this->chapterNotes()
            ->where('chapter_id', $chapterId)
            ->latest('id')
            ->first()
            ?->notes['chapter_note'] ?? null;
    }

    /**
     * Findings across all sections that reference the chapter and have not
     * been marked resolved by the user.
     *
     * @return list<array{key: string, section: string, severity: string, description: string, recommendation: string}>
     */
    public function unresolvedFindingsForChapter(int $chapterId): array
    {
        $resolvedKeys = $this->resolved_findings ?? [];

        return $this->sections
            ->flatMap(fn (EditorialReviewSection $section) => collect($section->findings ?? [])
                ->filter(fn (array $finding) => in_array($chapterId, array_map('intval', $finding['chapter_references'] ?? []), true)
                    && ! in_array($finding['key'] ?? '', $resolvedKeys, true))
                ->map(fn (array $finding) => [
                    'key' => $finding['key'] ?? '',
                    'section' => $section->type->value,
                    'severity' => $finding['severity'] ?? 'suggestion',
                    'description' => $finding['description'] ?? '',
                    'recommendation' => $finding['recommendation'] ?? '',
                ]))
            ->values()
            ->all();
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
