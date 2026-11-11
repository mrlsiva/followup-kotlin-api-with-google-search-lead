<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Carbon;

/**
 * Next follow-up can be sent either as
 *   next_followup_date (Y-m-d) + next_followup_time (H:i, default 10:00)
 * or as a single
 *   next_followup_at (any datetime, e.g. 2026-11-12 15:30 or ISO-8601).
 */
trait ParsesNextFollowup
{
    protected function nextFollowupRules(): array
    {
        return [
            'next_followup_date' => ['nullable', 'date_format:Y-m-d'],
            'next_followup_time' => ['nullable', 'date_format:H:i'],
            'next_followup_at' => ['nullable', 'date'],
        ];
    }

    /** True when the client sent any next-follow-up field (even null = clear). */
    public function hasNextFollowupInput(): bool
    {
        return $this->exists('next_followup_date') || $this->exists('next_followup_at');
    }

    public function nextFollowupAt(): ?Carbon
    {
        if ($this->filled('next_followup_at')) {
            return Carbon::parse($this->input('next_followup_at'))->setTimezone(config('app.timezone'))->seconds(0);
        }
        if ($this->filled('next_followup_date')) {
            return Carbon::createFromFormat('Y-m-d H:i', $this->input('next_followup_date').' '.($this->input('next_followup_time') ?: '10:00'))->seconds(0);
        }

        return null;
    }

    /** Ensure a supplied next follow-up is in the future. */
    protected function validateNextIsFuture($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $next = $this->nextFollowupAt();
            if ($next && $next->isPast()) {
                $v->errors()->add('next_followup_date', 'The next follow-up must be in the future.');
            }
            // A do-not-contact lead (Business Finder contact verification) cannot get new calls scheduled.
            $lead = $this->route('lead') ?? $this->route('followup')?->lead ?? $this->route('businessLead')?->lead;
            if ($next && $lead?->do_not_contact) {
                $v->errors()->add('next_followup_date', 'This lead is marked "Do not contact". Follow-ups cannot be scheduled.');
            }
        });
    }
}
