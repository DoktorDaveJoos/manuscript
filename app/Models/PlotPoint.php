<?php

namespace App\Models;

use App\Enums\PlotPointStatus;
use App\Enums\PlotPointType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlotPoint extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PlotPointType::class,
            'status' => PlotPointStatus::class,
            'sort_order' => 'integer',
            'is_ai_derived' => 'boolean',
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
     * @return BelongsTo<Chapter, $this>
     */
    public function intendedChapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'intended_chapter_id');
    }

    /**
     * @return BelongsTo<Chapter, $this>
     */
    public function actualChapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'actual_chapter_id');
    }
}
