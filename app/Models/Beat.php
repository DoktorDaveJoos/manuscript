<?php

namespace App\Models;

use App\Enums\BeatStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Beat extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BeatStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PlotPoint, $this>
     */
    public function plotPoint(): BelongsTo
    {
        return $this->belongsTo(PlotPoint::class);
    }

    /**
     * @return BelongsToMany<Chapter, $this>
     */
    public function chapters(): BelongsToMany
    {
        return $this->belongsToMany(Chapter::class, 'beat_chapter')
            ->withPivot(['sort_order'])
            ->withTimestamps();
    }
}
