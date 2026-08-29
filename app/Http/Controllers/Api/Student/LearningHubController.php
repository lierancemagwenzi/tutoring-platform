<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingTutorResource;
use App\Http\Resources\TeachingSessionResource;
use App\Models\TeachingSession;
use App\Models\User;
use App\Services\LearningHub\LearningHubService;
use App\Services\LearningHub\SessionsHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LearningHubController extends Controller
{
    public function __construct(
        protected SessionsHubService $sessions,
        protected LearningHubService $learning,
    ) {}

    /**
     * The Student Learning Hub home bundle — everything the home page needs
     * in a single request. Every section is a thin read over
     * SessionsHubService/LearningHubService; no business logic lives here.
     */
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();
        $todaysSessions = $this->sessions->todaysSessions($student);

        return response()->json([
            'stats' => $this->learning->homeStats($student, $todaysSessions->count()),
            'todays_sessions' => $this->sessionCards($todaysSessions, $student),
            'continue_learning' => $this->learning->continueLearning($student),
            'pending_activities' => $this->learning->pendingActivities($student),
            'recent_results' => $this->learning->recentResults($student),
            'performance_snapshot' => $this->learning->performanceSnapshot($student),
            'latest_feedback' => $this->learning->latestFeedback($student),
            'notifications' => $this->learning->notifications($student),
            'calendar_preview' => [
                'sessions' => $this->sessionCards($this->sessions->sessionsWithinDays($student, 14), $student),
                'deadlines' => collect($this->learning->pendingActivities($student, 20))
                    ->filter(fn (array $item) => $item['due_date'] !== null)
                    ->sortBy('due_date')
                    ->take(10)
                    ->values(),
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * The student's Scheduled sessions after today, paginated.
     */
    public function upcomingSessions(Request $request): JsonResponse
    {
        $student = $request->user();
        $sessions = $this->sessions->upcomingSessions($student, (int) ($request->query('per_page') ?? 10));

        return response()->json([
            'sessions' => $this->sessionCards(collect($sessions->items()), $student),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    /**
     * Wraps each TeachingSession with the tutor and the lesson titles
     * assigned to it, plus this student's own booking id (for linking back
     * to their Booking page) — a session can belong to more than one
     * booking (a group class), so the card resolves specifically this
     * student's booking rather than assuming there's only one.
     *
     * @param  Collection<int, TeachingSession>  $sessions
     */
    private function sessionCards(Collection $sessions, User $student): array
    {
        return $sessions->map(fn (TeachingSession $session) => [
            'booking_id' => $session->bookings->firstWhere('student_id', $student->id)?->id,
            'tutor' => new BookingTutorResource($session->tutorProfile),
            'session' => new TeachingSessionResource($session),
            'lessons' => $session->sessionLessons->pluck('lesson.title')->filter()->values(),
        ])->all();
    }
}
