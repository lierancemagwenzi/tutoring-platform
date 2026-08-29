<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\UpdateLearningActivityRequest;
use App\Http\Resources\LearningActivityResource;
use App\Models\LearningActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningActivityController extends Controller
{
    /**
     * Show a single learning activity belonging to the logged in tutor.
     */
    public function show(Request $request, LearningActivity $learningActivity): JsonResponse
    {
        abort_unless(
            $learningActivity->lesson->chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id,
            403,
        );

        return response()->json([
            'activity' => new LearningActivityResource($learningActivity->load('attachments')),
        ]);
    }

    /**
     * Update a learning activity's configuration.
     */
    public function update(UpdateLearningActivityRequest $request, LearningActivity $learningActivity): JsonResponse
    {
        $learningActivity->update([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'instructions' => [
                'html' => $request->validated('instructions_html'),
                'json' => $request->validated('instructions_json'),
            ],
            'status' => $request->validated('status'),
            'submission_type' => $request->validated('submission_type'),
            'max_score' => $request->validated('max_score'),
            'settings' => $request->validated('settings') ?? [],
        ]);

        return response()->json([
            'activity' => new LearningActivityResource($learningActivity->load('attachments')),
        ]);
    }

    /**
     * Delete a learning activity.
     */
    public function destroy(Request $request, LearningActivity $learningActivity): JsonResponse
    {
        abort_unless(
            $learningActivity->lesson->chapter->course->tutor_profile_id === $request->user()->tutorProfile?->id,
            403,
        );

        $learningActivity->delete();

        return response()->json([
            'message' => 'Learning activity deleted.',
        ]);
    }
}
