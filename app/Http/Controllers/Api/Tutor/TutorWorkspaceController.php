<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Resources\AvailabilityDateResource;
use App\Http\Resources\TeachingSessionResource;
use App\Http\Resources\TutorBookingResource;
use App\Models\TeachingSession;
use App\Services\TutorWorkspace\ActionCenterService;
use App\Services\TutorWorkspace\AvailabilityHubService;
use App\Services\TutorWorkspace\BookingRequestsHubService;
use App\Services\TutorWorkspace\ContentReleaseHubService;
use App\Services\TutorWorkspace\StudentWorkService;
use App\Services\TutorWorkspace\TeachingHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TutorWorkspaceController extends Controller
{
    public function __construct(
        protected TeachingHubService $teaching,
        protected BookingRequestsHubService $bookingRequests,
        protected StudentWorkService $studentWork,
        protected ContentReleaseHubService $contentRelease,
        protected AvailabilityHubService $availability,
        protected ActionCenterService $actionCenter,
    ) {}

    /**
     * The Tutor Workspace home bundle — everything the tutor's operational
     * landing page needs in a single request. Every section is a thin read
     * over the Tutor Workspace services; no business logic lives here.
     */
    public function index(Request $request): JsonResponse
    {
        $tutor = $request->user()->tutorProfile;
        abort_unless($tutor, 403);

        $todaysSessions = $this->teaching->todaysSessions($tutor);
        $pendingBookingRequests = $this->bookingRequests->pending($tutor);
        $pendingReviews = $this->studentWork->pendingReviews($tutor);
        $contentReleaseItems = $this->contentRelease->needsAction($tutor);
        $studentsRequiringAttention = $this->studentWork->studentsRequiringAttention($tutor);
        $sessionsStartingSoon = $this->teaching->sessionsStartingSoon($tutor);

        $performanceSnapshot = $this->studentWork->performanceSnapshot($tutor);
        $performanceSnapshot['session_attendance_rate'] = $this->teaching->attendanceRate($tutor);

        $todaysAvailability = $this->availability->todaysAvailability($tutor);

        return response()->json([
            'stats' => [
                'sessions_today' => $todaysSessions->count(),
                'upcoming_sessions' => $this->teaching->upcomingSessionCount($tutor),
                'active_students' => $this->studentWork->activeStudents($tutor)->count(),
                'pending_reviews' => count($pendingReviews),
                'pending_booking_requests' => $this->bookingRequests->pendingCount($tutor),
            ],
            'action_center' => $this->actionCenter->build(
                $pendingReviews,
                $pendingBookingRequests,
                $sessionsStartingSoon,
                $contentReleaseItems,
                $studentsRequiringAttention,
            ),
            'todays_sessions' => $this->sessionCards($todaysSessions),
            'booking_requests' => TutorBookingResource::collection($pendingBookingRequests),
            'students_requiring_attention' => $studentsRequiringAttention,
            'pending_reviews' => $pendingReviews,
            'recent_activity' => $this->studentWork->recentActivity($tutor),
            'performance_snapshot' => $performanceSnapshot,
            'content_release' => $contentReleaseItems,
            'availability' => [
                'today' => $todaysAvailability ? new AvailabilityDateResource($todaysAvailability) : null,
                'upcoming' => AvailabilityDateResource::collection($this->availability->upcomingAvailability($tutor)),
                'unavailable_dates' => $this->availability->unavailableDates($tutor),
            ],
            'calendar_preview' => [
                'sessions' => $this->sessionCards($this->teaching->sessionsWithinDays($tutor, 14)),
                'content_release' => $contentReleaseItems,
                'booking_requests' => TutorBookingResource::collection($pendingBookingRequests),
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * The tutor's confirmed sessions after today, paginated.
     */
    public function upcomingSessions(Request $request): JsonResponse
    {
        $tutor = $request->user()->tutorProfile;
        abort_unless($tutor, 403);

        $sessions = $this->teaching->upcomingSessions($tutor, (int) ($request->query('per_page') ?? 10));

        return response()->json([
            'sessions' => $this->sessionCards(collect($sessions->items())),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    /**
     * Wraps each session in TeachingSessionResource (unchanged from how
     * TeachingSessionController already exposes it) alongside the lesson
     * titles assigned to it, so a session card can show "Assigned Lesson"
     * without a new session resource shape.
     *
     * @param  Collection<int, TeachingSession>  $sessions
     */
    private function sessionCards(Collection $sessions): array
    {
        return $sessions->map(fn (TeachingSession $session) => [
            'session' => new TeachingSessionResource($session),
            'lessons' => $session->sessionLessons->pluck('lesson.title')->filter()->values(),
        ])->all();
    }
}
