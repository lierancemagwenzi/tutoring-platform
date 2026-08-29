<?php

namespace App\Http\Controllers\Api\Student;

use App\Enums\ChapterStatus;
use App\Enums\LessonBlockStatus;
use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Student\QuizResource;
use App\Models\Quiz;
use App\Services\Lms\StudentCourseAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function __construct(private readonly StudentCourseAccessService $accessService) {}

    /**
     * Show a published quiz, if the student has paid for a matching course.
     */
    public function show(Request $request, Quiz $quiz): JsonResponse
    {
        $lesson = $quiz->lesson;
        $chapter = $lesson->chapter;
        $course = $chapter->course;

        abort_unless(
            $quiz->status === LessonBlockStatus::Published
                && $lesson->status === LessonStatus::Published
                && $chapter->status === ChapterStatus::Published
                && $this->accessService->canAccess($request->user(), $course),
            403,
        );

        return response()->json([
            'quiz' => new QuizResource($quiz->load('questions')),
        ]);
    }
}
