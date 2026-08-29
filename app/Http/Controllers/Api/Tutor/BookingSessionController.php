<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ScheduleSessionRequest;
use App\Http\Resources\TeachingSessionResource;
use App\Http\Resources\TutorBookingResource;
use App\Models\Booking;
use App\Services\Booking\BookingProgressService;
use App\Services\Booking\SessionSchedulingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The tutor's booking management hub: the student/service/progress summary
 * plus the full session timeline, and the entry point for scheduling the
 * next session in the purchased package.
 */
class BookingSessionController extends Controller
{
    /**
     * @var list<string>
     */
    private const SESSION_WITH = ['service.subject', 'service.sessionFormat', 'sessionMeeting', 'sessionLessons.lesson', 'bookings.student'];

    /**
     * The booking's student/service/price plus dynamically-computed
     * progress and its full session timeline, ordered chronologically.
     */
    public function show(Request $request, Booking $booking, BookingProgressService $progress): JsonResponse
    {
        abort_unless($booking->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        $sessions = $booking->teachingSessions()
            ->with(self::SESSION_WITH)
            ->get()
            ->sortBy(fn ($session) => $session->date->format('Y-m-d').$session->start_time)
            ->values();

        return response()->json([
            'booking' => new TutorBookingResource($booking->load(['student', 'service.subject', 'service.category', 'service.sessionFormat'])),
            'progress' => $progress->progress($booking),
            'sessions' => TeachingSessionResource::collection($sessions),
        ]);
    }

    /**
     * Schedule one session for this booking's purchased package.
     */
    public function store(ScheduleSessionRequest $request, Booking $booking, SessionSchedulingService $scheduling): JsonResponse
    {
        try {
            $session = $scheduling->scheduleSession($booking, $request->validated());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'session' => new TeachingSessionResource($session),
        ], 201);
    }
}
