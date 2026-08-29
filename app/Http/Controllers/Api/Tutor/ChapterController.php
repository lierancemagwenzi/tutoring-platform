<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Enums\ChapterStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ReorderChaptersRequest;
use App\Http\Requests\Tutor\StoreChapterRequest;
use App\Http\Requests\Tutor\UpdateChapterRequest;
use App\Http\Resources\ChapterResource;
use App\Models\Chapter;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    /**
     * Return the chapters belonging to a course.
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        abort_unless($course->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        return response()->json([
            'chapters' => ChapterResource::collection($course->chapters),
        ]);
    }

    /**
     * Create a new chapter for a course.
     */
    public function store(StoreChapterRequest $request, Course $course): JsonResponse
    {
        $chapter = $course->chapters()->create([
            ...$request->safe()->except('status'),
            'position' => $course->chapters()->count(),
            'status' => $request->validated('status') ?? ChapterStatus::Draft->value,
        ]);

        return response()->json([
            'chapter' => new ChapterResource($chapter),
        ], 201);
    }

    /**
     * Update an existing chapter.
     */
    public function update(UpdateChapterRequest $request, Chapter $chapter): JsonResponse
    {
        $chapter->update($request->validated());

        return response()->json([
            'chapter' => new ChapterResource($chapter),
        ]);
    }

    /**
     * Delete a chapter.
     */
    public function destroy(Request $request, Chapter $chapter): JsonResponse
    {
        abort_unless($chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        $chapter->delete();

        return response()->json([
            'message' => 'Chapter deleted.',
        ]);
    }

    /**
     * Persist the drag-and-drop order of a course's chapters.
     */
    public function reorder(ReorderChaptersRequest $request, Course $course): JsonResponse
    {
        foreach ($request->validated('chapter_ids') as $position => $chapterId) {
            Chapter::whereKey($chapterId)->update(['position' => $position]);
        }

        return response()->json([
            'chapters' => ChapterResource::collection($course->chapters()->get()),
        ]);
    }
}
