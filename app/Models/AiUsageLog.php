<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function featureBreakdown(int $bookId, ?\DateTimeInterface $resetAt = null): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->where('book_id', $bookId)
            ->when($resetAt, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->selectRaw('feature, SUM(input_tokens + output_tokens) as tokens, SUM(cost_microdollars) as cost_micro')
            ->groupBy('feature')
            ->orderByDesc('tokens')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function monthlyUsage(int $bookId, ?\DateTimeInterface $resetAt = null, int $months = 5): \Illuminate\Database\Eloquent\Collection
    {
        $since = now()->subMonths($months)->startOfMonth();

        if ($resetAt && $resetAt->gt($since)) {
            $since = $resetAt;
        }

        return static::query()
            ->where('book_id', $bookId)
            ->where('created_at', '>=', $since)
            ->selectRaw("strftime('%Y-%m', created_at) as month, SUM(input_tokens + output_tokens) as tokens")
            ->groupByRaw("strftime('%Y-%m', created_at)")
            ->orderBy('month')
            ->get();
    }
}
