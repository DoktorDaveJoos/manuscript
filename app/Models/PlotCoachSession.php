<?php

namespace App\Models;

use App\Enums\CoachingMode;
use App\Enums\PlotCoachSessionStatus;
use App\Enums\PlotCoachStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlotCoachSession extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'book_id',
        'agent_conversation_id',
        'status',
        'stage',
        'coaching_mode',
        'decisions',
        'pending_board_changes',
        'input_tokens',
        'output_tokens',
        'cost_cents',
        'archived_at',
        'user_turn_count',
        'archive_summary',
        'parent_session_id',
        'rolling_digest',
        'rolling_digest_through_turn',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlotCoachSessionStatus::class,
            'stage' => PlotCoachStage::class,
            'coaching_mode' => CoachingMode::class,
            'decisions' => 'array',
            'pending_board_changes' => 'array',
            'archived_at' => 'datetime',
            'user_turn_count' => 'integer',
            'parent_session_id' => 'integer',
            'rolling_digest_through_turn' => 'integer',
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
     * @return HasMany<PlotCoachBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(PlotCoachBatch::class, 'session_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PlotCoachSessionStatus::Active);
    }

    public static function activeForBook(int $bookId): ?self
    {
        return self::query()
            ->where('book_id', $bookId)
            ->active()
            ->first();
    }
}
