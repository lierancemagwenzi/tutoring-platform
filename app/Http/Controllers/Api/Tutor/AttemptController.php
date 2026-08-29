<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttemptResource;
use App\Models\Attempt;
use App\Models\SessionLessonBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttemptController extends Controller
{
    /**
     * Every student's attempts against this delivery instance.
     */
    public function index(Request $request, SessionLessonBlock $sessionLessonBlock): JsonResponse
    {
        abort_unless(
            $sessionLessonBlock->sessionLesson->teachingSession->tutor_profile_id === $request->user()->tutorProfile?->id,
            403,
        );

        $attempts = $sessionLessonBlock->attempts()
            ->with('student')
            ->orderByDesc('started_at')
            ->get();

        return response()->json([
            'attempts' => AttemptResource::collection($attempts),
        ]);
    }

    /**
     * A single attempt, including the raw provider result for inspection.
     */
    public function show(Request $request, Attempt $attempt): JsonResponse
    {
        abort_unless(
            $attempt->sessionLessonBlock->sessionLesson->teachingSession->tutor_profile_id === $request->user()->tutorProfile?->id,
            403,
        );

        return response()->json([
            'attempt' => new AttemptResource($attempt->load('student')),
        ]);
    }
}
