<?php

namespace App\Jobs;

use App\Enums\MeetingStatus;
use App\Models\SessionMeeting;
use App\Services\Meetings\MeetingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Performs the real, potentially-slow/unreliable Google Calendar API call
 * for a session's meeting. Always dispatched with ->afterCommit() so it
 * only ever runs once the enclosing payment transaction has committed —
 * this job's failure must never be able to affect payment/order/booking
 * state, which is exactly why it exists as a separate job rather than an
 * inline call.
 */
class CreateSessionMeetingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    public $tries = 3;

    public function __construct(public int $sessionMeetingId) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(MeetingService $meetings): void
    {
        $sessionMeeting = SessionMeeting::find($this->sessionMeetingId);

        // Idempotent: nothing to do if the meeting is gone or already
        // Scheduled (e.g. a manual retry raced with this same job).
        if (! $sessionMeeting || $sessionMeeting->status === MeetingStatus::Scheduled) {
            return;
        }

        $meetings->attemptCreate($sessionMeeting);
    }

    public function failed(?Throwable $exception): void
    {
        SessionMeeting::where('id', $this->sessionMeetingId)->update([
            'status' => MeetingStatus::Failed,
        ]);

        Log::error('Meeting creation permanently failed after all retries.', [
            'session_meeting_id' => $this->sessionMeetingId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
