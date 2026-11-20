<?php

namespace Tests\Feature;

use App\Models\Followup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CallRecordingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00')); // a Tuesday
        Storage::fake('local');
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function createLead(): array
    {
        return $this->postJson('/api/leads', [
            'name' => 'Ravi Kumar', 'mobile' => '+91 98765 43210', 'source' => 'WhatsApp', 'priority' => 'High',
            'next_followup_date' => '2026-11-10', 'next_followup_time' => '15:00',
        ])->assertCreated()->json('data');
    }

    private function local(string $iso): Carbon
    {
        return Carbon::parse($iso)->setTimezone(config('app.timezone'));
    }

    private function upload(string $url, array $data)
    {
        return $this->post($url, $data, ['Accept' => 'application/json']);
    }

    public function test_note_stores_call_details_and_computes_duration(): void
    {
        $lead = $this->createLead();
        $started = Carbon::now()->subMinutes(30)->toIso8601String();
        $ended = Carbon::now()->subMinutes(30)->addSeconds(185)->toIso8601String();

        $note = $this->postJson("/api/leads/{$lead['id']}/followups", [
            'call_status' => 'Interested', 'note' => 'Wants a quote',
            'call_direction' => 'outgoing', 'phone_number' => '+919876543210',
            'call_started_at' => $started, 'call_ended_at' => $ended,
        ])->assertCreated()->json('data');

        $this->assertSame('outgoing', $note['call_direction']);
        $this->assertSame('+919876543210', $note['phone_number']);
        $this->assertSame(185, $note['call_duration']);
        $this->assertSame($this->local($started)->toIso8601String(), $note['call_started_at']);
        $this->assertSame($this->local($ended)->toIso8601String(), $note['call_ended_at']);
        // the note time follows the call start when date/time are not sent
        $this->assertSame($this->local($started)->format('H:i'), $note['time']);
        $this->assertFalse($note['has_recording']);
        $this->assertNull($note['recording']);
    }

    public function test_call_details_validation(): void
    {
        $lead = $this->createLead();

        $this->postJson("/api/leads/{$lead['id']}/followups", [
            'call_status' => 'Callback', 'call_direction' => 'sideways', 'call_duration' => -5,
            'call_started_at' => '2026-11-10T11:00:00+05:30', 'call_ended_at' => '2026-11-10T10:00:00+05:30',
        ])->assertUnprocessable()->assertJsonValidationErrors(['call_direction', 'call_duration', 'call_ended_at']);

        $this->postJson("/api/leads/{$lead['id']}/followups", [
            'call_status' => 'Callback', 'call_started_at' => Carbon::now()->addHour()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['date']);
    }

    public function test_complete_with_recording_in_the_same_request_and_play_it(): void
    {
        $lead = $this->createLead();
        $pendingId = $lead['followups'][0]['id'];

        $res = $this->upload("/api/followups/$pendingId/complete", [
            'call_status' => 'Interested', 'call_direction' => 'outgoing', 'call_duration' => 62,
            'recording' => UploadedFile::fake()->create('call_20261110.m4a', 300, 'audio/mp4'),
        ])->assertOk();

        $res->assertJsonPath('data.call_duration', 62)
            ->assertJsonPath('data.has_recording', true)
            ->assertJsonPath('data.recording.format', 'm4a')
            ->assertJsonPath('data.recording.mime', 'audio/mp4');
        $followup = Followup::find($pendingId);
        Storage::disk('local')->assertExists($followup->recording_path);
        $this->assertStringStartsWith("recordings/{$this->user->id}/", $followup->recording_path);

        $play = $this->get($res->json('data.recording.url'))->assertOk();
        $this->assertSame('audio/mp4', $play->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', $play->headers->get('Content-Disposition'));
        $download = $this->get("/api/followups/$pendingId/recording?download=1")->assertOk();
        $this->assertStringStartsWith('attachment', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString("call-2026-11-10-1200-lead{$lead['id']}.m4a", $download->headers->get('Content-Disposition'));
    }

    public function test_upload_later_replace_and_delete(): void
    {
        $lead = $this->createLead();
        $note = $this->postJson("/api/leads/{$lead['id']}/followups", ['call_status' => 'Callback'])->json('data');
        $url = "/api/followups/{$note['id']}/recording";

        $this->upload($url, ['recording' => UploadedFile::fake()->create('a.amr', 50, 'audio/amr')])
            ->assertOk()->assertJsonPath('data.recording.format', 'amr');
        $first = Followup::find($note['id'])->recording_path;

        $this->upload($url, ['recording' => UploadedFile::fake()->create('b.mp3', 50, 'audio/mpeg')])
            ->assertOk()->assertJsonPath('data.recording.mime', 'audio/mpeg');
        Storage::disk('local')->assertMissing($first);   // the replaced file is removed
        $second = Followup::find($note['id'])->recording_path;

        $this->deleteJson($url)->assertOk()->assertJsonPath('data.has_recording', false);
        Storage::disk('local')->assertMissing($second);
        $this->getJson($url)->assertNotFound();
    }

    public function test_rejects_bad_files_and_pending_followups(): void
    {
        $lead = $this->createLead();
        $note = $this->postJson("/api/leads/{$lead['id']}/followups", ['call_status' => 'Callback'])->json('data');
        $url = "/api/followups/{$note['id']}/recording";

        $this->upload($url, ['recording' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors(['recording']);
        $this->upload($url, ['recording' => UploadedFile::fake()->create('fake.m4a', 10, 'application/x-php')])
            ->assertUnprocessable()->assertJsonValidationErrors(['recording']);
        $this->upload($url, ['recording' => UploadedFile::fake()->create('big.m4a', config('crm.recordings.max_kb') + 1, 'audio/mp4')])
            ->assertUnprocessable()->assertJsonValidationErrors(['recording']);
        $this->assertFalse(Followup::find($note['id'])->hasRecording());

        $pendingId = $lead['followups'][0]['id'];
        $this->upload("/api/followups/$pendingId/recording", ['recording' => UploadedFile::fake()->create('a.m4a', 10, 'audio/mp4')])
            ->assertStatus(409);
    }

    public function test_other_users_cannot_access_recording_and_deleting_note_removes_file(): void
    {
        $lead = $this->createLead();
        $note = $this->upload("/api/leads/{$lead['id']}/followups", [
            'call_status' => 'Callback', 'recording' => UploadedFile::fake()->create('a.m4a', 20, 'audio/mp4'),
        ])->assertCreated()->json('data');
        $path = Followup::find($note['id'])->recording_path;

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/followups/{$note['id']}/recording")->assertNotFound();
        $this->deleteJson("/api/followups/{$note['id']}/recording")->assertNotFound();
        Storage::disk('local')->assertExists($path);

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/followups/{$note['id']}")->assertOk();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_web_timeline_shows_call_details_and_player(): void
    {
        $lead = $this->createLead();
        $note = $this->upload("/api/leads/{$lead['id']}/followups", [
            'call_status' => 'Interested', 'call_direction' => 'incoming', 'call_duration' => 125,
            'recording' => UploadedFile::fake()->create('a.m4a', 20, 'audio/mp4'),
        ])->assertCreated()->json('data');

        $this->actingAs($this->user, 'web')->get(route('leads.show', $lead['id']))->assertOk()
            ->assertSee('bi-telephone-inbound', false)
            ->assertSee('02:05')
            ->assertSee(route('followups.recording', $note['id']), false);
        $this->get(route('followups.recording', $note['id']))->assertOk();

        $this->actingAs(User::factory()->create(), 'web')->get(route('followups.recording', $note['id']))->assertNotFound();
    }

    public function test_options_list_directions_and_formats(): void
    {
        $this->getJson('/api/options')->assertOk()
            ->assertJsonPath('call_directions', ['outgoing', 'incoming'])
            ->assertJsonPath('recording_formats.0', 'm4a')
            ->assertJsonPath('recording_max_mb', 25);
    }
}
