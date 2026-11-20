<?php

namespace App\Models;

use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Enums\FollowupStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A follow-up. "pending" rows are scheduled reminders; "completed" rows are the
 * notes/history shown on the lead timeline (date, time, note, status after call).
 */
class Followup extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id', 'user_id', 'scheduled_at', 'status', 'completed_at', 'call_status', 'note',
        'call_direction', 'phone_number', 'call_started_at', 'call_ended_at', 'call_duration',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => FollowupStatus::class,
            'call_status' => CallStatus::class,
            'call_direction' => CallDirection::class,
            'call_started_at' => 'datetime',
            'call_ended_at' => 'datetime',
            'call_duration' => 'integer',
            'recording_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Never leave a recording file behind when its note is deleted.
        static::deleted(fn (Followup $f) => $f->deleteRecordingFile());
    }

    // ---------- Call recording ----------

    public function hasRecording(): bool
    {
        return $this->recording_path !== null;
    }

    /** Store (or replace) the call recording of this note. */
    public function attachRecording(UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs(
            "recordings/{$this->user_id}",
            $this->id.'_'.Str::random(20).'.'.$ext,
            ['disk' => self::recordingDisk()]
        );
        if ($path === false) {
            throw new \RuntimeException('Could not store the call recording.');
        }

        $this->deleteRecordingFile();
        $this->forceFill([
            'recording_path' => $path,
            'recording_mime' => config("crm.recordings.formats.$ext", 'application/octet-stream'),
            'recording_size' => $file->getSize(),
        ])->save();
    }

    public function removeRecording(): void
    {
        $this->deleteRecordingFile();
        $this->forceFill(['recording_path' => null, 'recording_mime' => null, 'recording_size' => null])->save();
    }

    /** File extension of the stored recording (m4a, mp3 …). */
    public function recordingFormat(): ?string
    {
        return $this->recording_path ? strtolower(pathinfo($this->recording_path, PATHINFO_EXTENSION)) : null;
    }

    /** Friendly download name, e.g. call-2026-11-20-1430-lead12.m4a */
    public function recordingFileName(): string
    {
        $at = $this->call_started_at ?? $this->completed_at ?? $this->scheduled_at;

        return 'call-'.$at->format('Y-m-d-Hi').'-lead'.$this->lead_id.'.'.$this->recordingFormat();
    }

    /**
     * Serve the recording. Local files go through BinaryFileResponse, which supports Range requests
     * (seeking in Android MediaPlayer / ExoPlayer and the browser's <audio>).
     */
    public function recordingResponse(bool $download = false): Response
    {
        $disk = self::recordingDisk();
        $headers = ['Content-Type' => $this->recording_mime ?? 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff'];
        $disposition = $download ? 'attachment' : 'inline';

        if (! Storage::disk($disk)->exists($this->recording_path)) {
            abort(404, 'Recording file not found.');
        }
        if (config("filesystems.disks.$disk.driver") === 'local') {
            return response()->file(Storage::disk($disk)->path($this->recording_path), $headers)
                ->setContentDisposition($disposition, $this->recordingFileName());
        }

        return Storage::disk($disk)->response($this->recording_path, $this->recordingFileName(), $headers, $disposition);
    }

    public static function recordingDisk(): string
    {
        return config('crm.recordings.disk', 'local');
    }

    private function deleteRecordingFile(): void
    {
        if ($this->recording_path) {
            Storage::disk(self::recordingDisk())->delete($this->recording_path);
        }
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
