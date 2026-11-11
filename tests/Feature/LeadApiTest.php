<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 09:00:00')); // a Tuesday
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function createLead(array $overrides = []): array
    {
        return $this->postJson('/api/leads', array_merge([
            'name' => 'Ravi Kumar',
            'mobile' => '+91 98765 43210',
            'company' => 'Acme Traders',
            'source' => 'WhatsApp',
            'priority' => 'High',
            'next_followup_date' => '2026-11-10',
            'next_followup_time' => '15:00',
        ], $overrides))->assertCreated()->json('data');
    }

    public function test_login_returns_token_and_guests_get_401(): void
    {
        $user = User::factory()->create(['email' => 'a@b.com', 'password' => bcrypt('secret123')]);
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/auth/login', ['email' => 'a@b.com', 'password' => 'secret123'])
            ->assertOk()->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'email']]);
        $this->postJson('/api/auth/login', ['email' => 'a@b.com', 'password' => 'nope'])->assertUnprocessable();
        $this->get('/api/leads')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_create_lead_creates_pending_followup(): void
    {
        $lead = $this->createLead();

        $this->assertSame('2026-11-10', $lead['next_followup_date']);
        $this->assertSame('15:00', $lead['next_followup_time']);
        $this->assertCount(1, $lead['followups']);
        $this->assertSame('pending', $lead['followups'][0]['status']);
    }

    public function test_validation_errors(): void
    {
        $this->postJson('/api/leads', ['mobile' => 'abc', 'source' => 'Fax'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'mobile', 'source']);
        $this->postJson('/api/leads', ['name' => 'X', 'next_followup_date' => '2026-11-01'])
            ->assertUnprocessable()->assertJsonValidationErrors(['next_followup_date']);
    }

    public function test_search_and_filters(): void
    {
        $this->createLead();
        $this->createLead(['name' => 'Priya', 'mobile' => '9000000001', 'company' => 'Bright Solar', 'status' => 'Won']);

        $this->getJson('/api/leads?search=acme')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ravi Kumar');
        $this->getJson('/api/leads?search=90000')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Priya');
        $this->getJson('/api/leads?status=Won')->assertJsonCount(1, 'data');
        $this->getJson('/api/leads?sort=priority')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_update_moves_and_clears_next_followup(): void
    {
        $lead = $this->createLead();

        $this->putJson("/api/leads/{$lead['id']}", ['next_followup_date' => '2026-11-12', 'next_followup_time' => '11:30'])
            ->assertOk()->assertJsonPath('data.next_followup_date', '2026-11-12')->assertJsonCount(1, 'data.followups');

        $this->putJson("/api/leads/{$lead['id']}", ['status' => 'Won'])
            ->assertOk()->assertJsonPath('data.next_followup_date', '2026-11-12'); // untouched when not sent

        $this->putJson("/api/leads/{$lead['id']}", ['next_followup_date' => null])
            ->assertOk()->assertJsonPath('data.next_followup_at', null)->assertJsonCount(0, 'data.followups');
    }

    public function test_complete_followup_auto_schedules_next(): void
    {
        $lead = $this->createLead(['priority' => 'Medium']);
        $fid = $lead['followups'][0]['id'];

        $res = $this->postJson("/api/followups/{$fid}/complete", ['call_status' => 'Interested', 'note' => 'Liked it'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('lead.status', 'Interested');
        // Medium + Interested → +3 days, keeps 15:00
        $this->assertSame('2026-11-13', $res->json('next_followup.date'));
        $this->assertSame('15:00', $res->json('next_followup.time'));

        $this->postJson("/api/followups/{$fid}/complete", ['call_status' => 'Closed'])->assertStatus(409);
    }

    public function test_closed_call_clears_schedule_and_explicit_date_wins(): void
    {
        $lead = $this->createLead();
        $fid = $lead['followups'][0]['id'];
        $this->postJson("/api/followups/{$fid}/complete", ['call_status' => 'Closed'])
            ->assertOk()->assertJsonPath('next_followup', null)->assertJsonPath('lead.next_followup_at', null);

        $lead2 = $this->createLead(['name' => 'B', 'mobile' => '9000000002']);
        $this->postJson("/api/followups/{$lead2['followups'][0]['id']}/complete", [
            'call_status' => 'Callback', 'next_followup_date' => '2026-11-20', 'next_followup_time' => '09:30',
        ])->assertJsonPath('next_followup.date', '2026-11-20')->assertJsonPath('next_followup.time', '09:30');
    }

    public function test_add_note_and_timeline(): void
    {
        $lead = $this->createLead();

        $this->postJson("/api/leads/{$lead['id']}/followups", [
            'date' => '2026-11-09', 'time' => '16:00', 'note' => 'First call', 'call_status' => 'Not Answered',
        ])->assertCreated()->assertJsonPath('data.date', '2026-11-09')->assertJsonPath('lead.next_followup_time', '15:00');

        $this->postJson("/api/leads/{$lead['id']}/followups", ['date' => '2026-12-01', 'call_status' => 'Callback'])
            ->assertUnprocessable()->assertJsonValidationErrors(['date']);

        $timeline = $this->getJson("/api/leads/{$lead['id']}/followups")->assertOk()->json('data');
        $this->assertSame(['pending', 'completed'], array_column($timeline, 'status'));
    }

    public function test_followup_lists_and_overdue(): void
    {
        $this->createLead(['next_followup_date' => '2026-11-10', 'next_followup_time' => '10:00']);
        $this->createLead(['name' => 'B', 'mobile' => '9000000002', 'next_followup_date' => '2026-11-15']);
        Carbon::setTestNow('2026-11-10 12:00:00');

        $this->getJson('/api/followups?type=overdue')->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_overdue', true);
        $this->getJson('/api/followups?type=today')->assertJsonCount(1, 'data');
        $this->getJson('/api/followups?type=upcoming')->assertJsonCount(1, 'data')->assertJsonPath('data.0.lead.name', 'B');
        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('stats.total_leads', 2)
            ->assertJsonPath('stats.today_followups', 1)
            ->assertJsonPath('stats.pending_followups', 2)
            ->assertJsonPath('stats.overdue_followups', 1);
        $this->getJson('/api/followups/daily-summary')->assertJsonPath('should_notify', true);
        $this->getJson('/api/followups/reminders?days=7')->assertJsonCount(1, 'data');
    }

    public function test_users_cannot_see_each_others_leads(): void
    {
        $lead = $this->createLead();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/leads/{$lead['id']}")->assertNotFound()->assertJson(['message' => 'Record not found.']);
        $this->getJson('/api/leads')->assertJsonCount(0, 'data');
        $this->postJson("/api/followups/{$lead['followups'][0]['id']}/complete", ['call_status' => 'Closed'])->assertNotFound();
    }

    public function test_delete_lead_removes_pending_followups(): void
    {
        $lead = $this->createLead();
        $this->deleteJson("/api/leads/{$lead['id']}")->assertOk();

        $this->getJson("/api/leads/{$lead['id']}")->assertNotFound();
        $this->getJson('/api/followups')->assertJsonCount(0, 'data');
        $this->assertSoftDeleted('leads', ['id' => $lead['id']]);
    }

    public function test_export_and_import_round_trip(): void
    {
        $this->createLead();

        foreach (['csv', 'xlsx'] as $format) {
            $res = $this->get("/api/leads/export?format={$format}")->assertOk();
            $path = tempnam(sys_get_temp_dir(), 't').".{$format}";
            file_put_contents($path, $res->streamedContent());

            $this->post('/api/leads/import', ['file' => new UploadedFile($path, "leads.{$format}", null, null, true)], ['Accept' => 'application/json'])
                ->assertOk()->assertJson(['created' => 0, 'updated' => 1, 'skipped' => 0]);
            @unlink($path);
        }

        $csv = "Name,Phone,Lead Source,Priority,Next Follow-up Date,Next Follow-up Time\n"
            ."New Person,9123456789,walk in,HIGH,2026-11-20,14:00\n,999,,,,\n";
        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);
        $this->post('/api/leads/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['created' => 1, 'updated' => 0, 'skipped' => 1]);

        $new = Lead::where('name', 'New Person')->first();
        $this->assertSame('Walk-in', $new->source->value);
        $this->assertSame('High', $new->priority->value);
        $this->assertSame('2026-11-20 14:00', $new->next_followup_at->format('Y-m-d H:i'));
    }
}
