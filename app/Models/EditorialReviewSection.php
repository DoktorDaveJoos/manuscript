<?php

namespace App\Models;

use App\Enums\EditorialSectionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorialReviewSection extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EditorialSectionType::class,
            'findings' => 'array',
            'recommendations' => 'array',
            'score' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<EditorialReview, $this>
     */
    public function editorialReview(): BelongsTo
    {
        return $this->belongsTo(EditorialReview::class);
    }
}
