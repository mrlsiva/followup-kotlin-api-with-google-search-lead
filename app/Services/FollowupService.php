<?php

namespace App\Services;

use App\Enums\CallStatus;
use App\Enums\FollowupStatus;
use App\Enums\LeadPriority;
use App\Enums\LeadStatus;
use App\Models\Followup;
use App\Models\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Business rules for follow-ups.
 *
 * - A lead has at most ONE pending follow-up; `leads.next_followup_at` mirrors it.
 * - Completing a follow-up records the call outcome and (optionally) schedules the next one.
 * - The next date can be supplied explicitly or auto-suggested from call status + lead priority.
 */
class FollowupService
{
    /**
     * Make the lead's single pending follow-up match $at (create / move / cancel).
     */
    public function syncNextFollowup(Lead $lead, ?Carbon $at): void
    {
        if ($lead->do_not_contact) {
            $at = null;     // never schedule calls to a do-not-contact lead (controllers reject it explicitly)
        }

        DB::transaction(function () use ($lead, $at) {
            $pending = $lead->followups()->pending()->orderBy('scheduled_at')->get();

            if ($at === null) {
                $lead->followups()->pending()->delete();
            } else {
                $keep = $pending->shift();
                $pending->each->delete();   // safety: never more than one pending
                if ($keep) {
                    if (! $keep->scheduled_at->equalTo($at)) {
                        $keep->update(['scheduled_at' => $at]);
                    }
                } else {
                    $lead->followups()->create([
                        'user_id' => $lead->user_id,
                        'scheduled_at' => $at,
                        'status' => FollowupStatus::Pending,
                    ]);
                }
            }

            $current = $lead->next_followup_at;
            $same = ($current === null && $at === null) || ($current && $at && $current->equalTo($at));
            if (! $same) {
                $lead->forceFill(['next_followup_at' => $at])->save();
            }
        });
    }

    /**
     * Add a history note to a lead (a completed follow-up at $at) and optionally reschedule.
     *
     * @param  array{at: Carbon, note: ?string, call_status: CallStatus, next_at: ?Carbon, schedule_next: ?bool}  $data
     */
    public function addNote(Lead $lead, array $data): Followup
    {
        return DB::transaction(function () use ($lead, $data) {
            $note = $lead->followups()->create([
                'user_id' => $lead->user_id,
                'scheduled_at' => $data['at'],
                'completed_at' => $data['at'],
                'status' => FollowupStatus::Completed,
                'call_status' => $data['call_status'],
                'note' => $data['note'] ?? null,
            ]);

            $this->applyCallOutcome($lead, $data['call_status']);
            $next = $this->resolveNext($lead, $data, $lead->next_followup_at);
            if ($next !== false) {
                $this->syncNextFollowup($lead, $next);
            }

            return $note;
        });
    }

    /**
     * Mark a pending follow-up done and schedule the next one.
     *
     * @param  array{note: ?string, call_status: CallStatus, next_at: ?Carbon, schedule_next: ?bool, completed_at?: ?Carbon}  $data
     */
    public function complete(Followup $followup, array $data): Followup
    {
        return DB::transaction(function () use ($followup, $data) {
            $followup->update([
                'status' => FollowupStatus::Completed,
                'completed_at' => $data['completed_at'] ?? now(),
                'call_status' => $data['call_status'],
                'note' => $data['note'] ?? null,
            ]);

            $lead = $followup->lead;
            $this->applyCallOutcome($lead, $data['call_status']);

            // When completing, "keep current schedule" makes no sense: default to auto-schedule.
            $data['schedule_next'] ??= true;
            $next = $this->resolveNext($lead, $data, $followup->scheduled_at);
            $this->syncNextFollowup($lead, $next === false ? null : $next);

            return $followup->refresh();
        });
    }

    /**
     * Suggest the next follow-up from the call outcome and priority. Keeps the time of day of
     * $base (10:00 if null), skips Sundays. Returns null for "Closed".
     */
    public function suggestNext(CallStatus $callStatus, LeadPriority $priority, ?Carbon $base = null): ?Carbon
    {
        $days = match ($callStatus) {
            CallStatus::NotAnswered, CallStatus::Callback => 1,
            CallStatus::Interested => match ($priority) {
                LeadPriority::High => 1,
                LeadPriority::Medium => 3,
                LeadPriority::Low => 5,
            },
            CallStatus::Closed => null,
        };
        if ($days === null) {
            return null;
        }

        $next = Carbon::today()->addDays($days)
            ->setTime($base?->hour ?? 10, $base?->minute ?? 0);
        if ($next->isSunday()) {
            $next->addDay();
        }

        return $next;
    }

    /**
     * Decide the next follow-up time.
     * Returns a Carbon (schedule at), null (clear the schedule) or false (leave it unchanged).
     */
    private function resolveNext(Lead $lead, array $data, ?Carbon $base): Carbon|null|false
    {
        if (! empty($data['next_at'])) {
            return $data['next_at'];
        }
        $scheduleNext = $data['schedule_next'] ?? null;
        if ($scheduleNext === false) {
            return null;            // explicitly "no next follow-up"
        }
        if ($scheduleNext === true || $data['call_status'] === CallStatus::Closed) {
            return $this->suggestNext($data['call_status'], $lead->priority, $base);
        }

        return false;               // addNote without instructions: keep current schedule
    }

    /** Light status automation that follows obvious call outcomes. */
    private function applyCallOutcome(Lead $lead, CallStatus $callStatus): void
    {
        $status = match (true) {
            $callStatus === CallStatus::Interested && in_array($lead->status, [LeadStatus::New, LeadStatus::FollowUp], true) => LeadStatus::Interested,
            in_array($callStatus, [CallStatus::Callback, CallStatus::NotAnswered], true) && $lead->status === LeadStatus::New => LeadStatus::FollowUp,
            default => null,
        };
        if ($status) {
            $lead->update(['status' => $status]);
        }
    }
}
