<?php

namespace App\Models;

use App\Enums\PlotPointStatus;
use App\Enums\PlotPointType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'tension_score' => 'integer',
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

    /**
     * @return HasMany<PlotPointConnection, $this>
     */
    public function outgoingConnections(): HasMany
    {
        return $this->hasMany(PlotPointConnection::class, 'source_plot_point_id');
    }

    /**
     * @return HasMany<PlotPointConnection, $this>
     */
    public function incomingConnections(): HasMany
    {
        return $this->hasMany(PlotPointConnection::class, 'target_plot_point_id');
    }

    /**
     * @return BelongsToMany<Character, $this>
     */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class)
            ->withPivot('role')
            ->withTimestamps();
    }
}
