<?php

namespace App\Models;

use App\Enums\WikiEntryKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WikiEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => WikiEntryKind::class,
            'metadata' => 'array',
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
        return $this->belongsToMany(Chapter::class, 'wiki_entry_chapter')
            ->withPivot(['notes'])
            ->withTimestamps();
    }

    /**
     * Get the combined description (manual + AI) for use in AI agent context.
     */
    public function fullDescription(): ?string
    {
        $parts = array_filter([
            $this->description,
            $this->ai_description,
        ]);

        return $parts ? implode("\n\n", $parts) : null;
    }
}
