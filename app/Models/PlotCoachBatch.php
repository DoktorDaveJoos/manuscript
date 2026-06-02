<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlotCoachBatch extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_id',
        'summary',
        'payload',
        'applied_at',
        'reverted_at',
        'undo_window_expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'applied_at' => 'datetime',
            'reverted_at' => 'datetime',
            'undo_window_expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PlotCoachSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PlotCoachSession::class, 'session_id');
    }
}
