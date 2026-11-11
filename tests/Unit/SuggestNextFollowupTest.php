<?php

namespace Tests\Unit;

use App\Enums\CallStatus;
use App\Enums\LeadPriority;
use App\Services\FollowupService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SuggestNextFollowupTest extends TestCase
{
    public function test_auto_schedule_rules(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00'); // Tuesday
        $s = new FollowupService;
        $base = Carbon::parse('2026-11-10 15:30');

        $this->assertSame('2026-11-11 15:30', $s->suggestNext(CallStatus::NotAnswered, LeadPriority::Low, $base)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-11 15:30', $s->suggestNext(CallStatus::Callback, LeadPriority::Medium, $base)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-11 15:30', $s->suggestNext(CallStatus::Interested, LeadPriority::High, $base)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-13 15:30', $s->suggestNext(CallStatus::Interested, LeadPriority::Medium, $base)->format('Y-m-d H:i'));
        // +5 days = Sunday 15th → moved to Monday
        $this->assertSame('2026-11-16 15:30', $s->suggestNext(CallStatus::Interested, LeadPriority::Low, $base)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-11 10:00', $s->suggestNext(CallStatus::Callback, LeadPriority::Low)->format('Y-m-d H:i'));
        $this->assertNull($s->suggestNext(CallStatus::Closed, LeadPriority::High, $base));
    }
}
