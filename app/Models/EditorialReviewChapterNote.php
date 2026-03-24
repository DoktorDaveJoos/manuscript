<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorialReviewChapterNote extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EditorialReview, $this>
     */
    public function editorialReview(): BelongsTo
    {
        return $this->belongsTo(EditorialReview::class);
    }

    /**
     * @return BelongsTo<Chapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
