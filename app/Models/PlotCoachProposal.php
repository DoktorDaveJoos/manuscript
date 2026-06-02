<?php

namespace App\Models;

use App\Enums\PlotCoachProposalKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A pending (or resolved) batch proposal emitted by the Plot Coach.
 *
 * Source-of-truth record for server-side batch approval. When the AI calls
 * ProposeBatch or ProposeChapterPlan, the tool persists a row with a uuid
 * public_id. The frontend's approval button sends APPROVE:batch:<public_id>,
 * which the controller intercepts and applies deterministically.
 */
class PlotCoachProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'session_id',
        'kind',
        'writes',
        'summary',
        'approved_at',
        'cancelled_at',
        'applied_batch_id',
    ];

    protected $casts = [
        'kind' => PlotCoachProposalKind::class,
        'writes' => 'array',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(PlotCoachSession::class, 'session_id');
    }

    public function appliedBatch(): BelongsTo
    {
        return $this->belongsTo(PlotCoachBatch::class, 'applied_batch_id');
    }

    /**
     * Record a new proposal against the given session and return the
     * public uuid that will appear in the frontend sentinel.
     *
     * @param  array<int, array<string, mixed>>  $writes
     */
    public static function record(
        PlotCoachSession $session,
        PlotCoachProposalKind $kind,
        array $writes,
        string $summary,
    ): string {
        $public = (string) Str::uuid();

        self::create([
            'public_id' => $public,
            'session_id' => $session->id,
            'kind' => $kind,
            'writes' => $writes,
            'summary' => $summary,
        ]);

        return $public;
    }

    public static function findForSession(PlotCoachSession $session, string $publicId): ?self
    {
        return self::query()
            ->where('session_id', $session->id)
            ->where('public_id', $publicId)
            ->first();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('approved_at')->whereNull('cancelled_at');
    }
}
