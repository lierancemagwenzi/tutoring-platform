<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\CompleteAttemptRequest;
use App\Http\Requests\Student\MarkAttemptInProgressRequest;
use App\Http\Requests\Student\StoreAttemptRequest;
use App\Http\Resources\Student\AttemptResource;
use App\Models\Attempt;
use App\Models\Booking;
use App\Models\LessonBlock;
use App\Services\Attempts\AttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttemptController extends Controller
{
    public function __construct(protected AttemptService $service) {}

    /**
     * The logged in student's own attempt history for this delivery instance.
     */
    public function index(Request $request, Booking $booking, LessonBlock $lessonBlock): JsonResponse
    {
        abort_unless($booking->student_id === $request->user()->id, 403);

        $sessionLessonBlock = $booking->sessionLessonBlockFor($lessonBlock);
        abort_unless($sessionLessonBlock, 404);

        $attempts = $sessionLessonBlock->attempts()
            ->where('student_id', $request->user()->id)
            ->orderByDesc('attempt_number')
            ->get();

        return response()->json([
            'attempts' => AttemptResource::collection($attempts),
        ]);
    }

    /**
     * Launch the activity: starts a new attempt, or resumes the currently open one.
     */
    public function store(StoreAttemptRequest $request, Booking $booking, LessonBlock $lessonBlock): JsonResponse
    {
        $sessionLessonBlock = $booking->sessionLessonBlockFor($lessonBlock);

        $attempt = $this->service->start($sessionLessonBlock, $lessonBlock, $request->user());

        return response()->json([
            'attempt' => new AttemptResource($attempt),
        ], 201);
    }

    /**
     * Mark an attempt as actively under way, once the provider's player has initialized.
     */
    public function markInProgress(MarkAttemptInProgressRequest $request, Attempt $attempt): JsonResponse
    {
        $this->service->markInProgress($attempt);

        return response()->json([
            'attempt' => new AttemptResource($attempt->fresh()),
        ]);
    }

    /**
     * Complete an attempt with the provider's raw result, scoring it immediately.
     */
    public function complete(CompleteAttemptRequest $request, Attempt $attempt): JsonResponse
    {
        $this->service->complete($attempt, $request->validated('raw_result'));

        return response()->json([
            'attempt' => new AttemptResource($attempt->fresh()),
        ]);
    }
}
