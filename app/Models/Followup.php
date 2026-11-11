<?php

namespace App\Models;

use App\Enums\CallStatus;
use App\Enums\FollowupStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A follow-up. "pending" rows are scheduled reminders; "completed" rows are the
 * notes/history shown on the lead timeline (date, time, note, status after call).
 */
class Followup extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id', 'user_id', 'scheduled_at', 'status', 'completed_at', 'call_status', 'note',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => FollowupStatus::class,
            'call_status' => CallStatus::class,
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === FollowupStatus::Pending;
    }

    public function isOverdue(): bool
    {
        return $this->isPending() && $this->scheduled_at->isPast();
    }

    // ---------- Scopes ----------

    /** Only follow-ups whose lead still exists (not soft-deleted). */
    public function scopeActiveLead(Builder $q): Builder
    {
        return $q->whereHas('lead', fn (Builder $l) => $l->whereNull('deleted_at'));
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', FollowupStatus::Pending);
    }

    public function scopeCompleted(Builder $q): Builder
    {
        return $q->where('status', FollowupStatus::Completed);
    }

    /** Pending and scheduled for today (items already past their time are also flagged is_overdue). */
    public function scopeToday(Builder $q): Builder
    {
        return $q->pending()->whereBetween('scheduled_at', [Carbon::today(), Carbon::today()->endOfDay()]);
    }

    /** Pending and due on any day after today. */
    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->pending()->where('scheduled_at', '>', Carbon::today()->endOfDay());
    }

    /** Pending and past its scheduled time. */
    public function scopeOverdue(Builder $q): Builder
    {
        return $q->pending()->where('scheduled_at', '<', now());
    }
}
