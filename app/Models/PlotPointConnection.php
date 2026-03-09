<?php

namespace App\Models;

use App\Enums\ConnectionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlotPointConnection extends Model
{
    /** @use HasFactory<\Database\Factories\PlotPointConnectionFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConnectionType::class,
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
     * @return BelongsTo<PlotPoint, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(PlotPoint::class, 'source_plot_point_id');
    }

    /**
     * @return BelongsTo<PlotPoint, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(PlotPoint::class, 'target_plot_point_id');
    }
}
