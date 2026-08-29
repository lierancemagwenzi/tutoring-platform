<?php

namespace App\Jobs;

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
 * Performs the real, potentially-slow/unreliable Google Calendar API call to
 * delete a cancelled session's meeting event. The meeting's local status is
 * already Cancelled by the time this runs, so this job's failure never
 * blocks or reverts session cancellation — at worst it leaves a stray
 * remote event, which retries (and eventually failed()) log for follow-up.
 */
class CancelSessionMeetingJob implements ShouldQueue
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

        if (! $sessionMeeting) {
            return;
        }

        $meetings->attemptCancel($sessionMeeting);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Meeting deletion permanently failed after all retries.', [
            'session_meeting_id' => $this->sessionMeetingId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
