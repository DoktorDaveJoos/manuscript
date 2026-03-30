<?php

namespace App\Models;

use Database\Factories\HealthSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthSnapshot extends Model
{
    /** @use HasFactory<HealthSnapshotFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'composite_score' => 'integer',
            'hooks_score' => 'integer',
            'pacing_score' => 'integer',
            'tension_score' => 'integer',
            'weave_score' => 'integer',
            'scene_purpose_score' => 'integer',
            'tension_dynamics_score' => 'integer',
            'emotional_arc_score' => 'integer',
            'craft_score' => 'integer',
            'recorded_at' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
