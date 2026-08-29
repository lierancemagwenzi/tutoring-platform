<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TeachingSessionResource;
use App\Models\TeachingSession;
use App\Services\Booking\SessionSchedulingService;
use App\Services\Meetings\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TeachingSessionController extends Controller
{
    /**
     * The relations eager-loaded on every teaching session response.
     *
     * @var list<string>
     */
    private const WITH = [
        'service.subject',
        'service.category',
        'service.sessionFormat',
        'sessionMeeting',
        'bookings.student',
    ];

    /**
     * Return the logged in tutor's teaching sessions.
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = $request->user()->tutorProfile
            ->teachingSessions()
            ->with(self::WITH)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'sessions' => TeachingSessionResource::collection($sessions),
        ]);
    }

    /**
     * Show a single teaching session belonging to the logged in tutor.
     */
    public function show(Request $request, TeachingSession $session): JsonResponse
    {
        abort_unless($session->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        return response()->json([
            'session' => new TeachingSessionResource($session->load(self::WITH)),
        ]);
    }

    /**
     * Re-queue meeting creation for a session whose meeting is Failed (or
     * still stuck Pending). Rejects an already-Scheduled meeting since
     * there is nothing to retry.
     */
    public function retryMeeting(Request $request, TeachingSession $session, MeetingService $meetings): JsonResponse
    {
        abort_unless($session->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        $sessionMeeting = $session->sessionMeeting;

        abort_if(! $sessionMeeting, 404, 'This session has no meeting to retry.');
        abort_if($sessionMeeting->status === MeetingStatus::Scheduled, 422, 'This meeting has already been scheduled.');

        $meetings->retry($sessionMeeting);

        return response()->json([
            'session' => new TeachingSessionResource($session->load(self::WITH)),
        ]);
    }

    /**
     * Mark a session Completed.
     */
    public function complete(Request $request, TeachingSession $session, SessionSchedulingService $scheduling): JsonResponse
    {
        abort_unless($session->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        try {
            $scheduling->completeSession($session, $request->input('tutor_notes'));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'session' => new TeachingSessionResource($session->load(self::WITH)),
        ]);
    }

    /**
     * Cancel a session. Never deleted — frees its availability window and
     * lets the booking schedule another session in its place.
     */
    public function cancel(Request $request, TeachingSession $session, SessionSchedulingService $scheduling): JsonResponse
    {
        abort_unless($session->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        try {
            $scheduling->cancelSession($session);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'session' => new TeachingSessionResource($session->load(self::WITH)),
        ]);
    }
}
