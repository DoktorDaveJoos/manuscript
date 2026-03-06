<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPreparation extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_chapters' => 'integer',
            'processed_chapters' => 'integer',
            'embedded_chunks' => 'integer',
            'current_phase_total' => 'integer',
            'current_phase_progress' => 'integer',
            'completed_phases' => 'array',
            'phase_errors' => 'array',
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
