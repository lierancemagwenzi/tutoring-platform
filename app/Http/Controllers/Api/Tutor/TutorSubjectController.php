<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Enums\TutorSubjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\AssignTutorSubjectRequest;
use App\Http\Requests\Tutor\UpdateTutorSubjectRequest;
use App\Http\Resources\TutorSubjectResource;
use App\Models\TutorSubject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorSubjectController extends Controller
{
    /**
     * Return the logged in tutor's assigned subjects.
     */
    public function index(Request $request): JsonResponse
    {
        $tutorSubjects = $request->user()->tutorProfile
            ->tutorSubjects()
            ->with(['subject', 'grades'])
            ->get();

        return response()->json([
            'subjects' => TutorSubjectResource::collection($tutorSubjects),
        ]);
    }

    /**
     * Assign a subject to the logged in tutor.
     */
    public function store(AssignTutorSubjectRequest $request): JsonResponse
    {
        $tutorSubject = $request->user()->tutorProfile
            ->tutorSubjects()
            ->create([
                'subject_id' => $request->validated('subject_id'),
                'status' => TutorSubjectStatus::Pending->value,
            ]);

        $this->syncGrades($tutorSubject, $request->validated('grade_ids'));

        return response()->json([
            'subject' => new TutorSubjectResource($tutorSubject->load(['subject', 'grades'])),
        ], 201);
    }

    /**
     * Update the grades for an assigned subject.
     */
    public function update(UpdateTutorSubjectRequest $request, TutorSubject $subject): JsonResponse
    {
        $this->syncGrades($subject, $request->validated('grade_ids'));

        return response()->json([
            'subject' => new TutorSubjectResource($subject->load(['subject', 'grades'])),
        ]);
    }

    /**
     * Remove a subject from the logged in tutor's profile.
     */
    public function destroy(Request $request, TutorSubject $subject): JsonResponse
    {
        abort_unless($subject->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        $subject->delete();

        return response()->json([
            'message' => 'Subject removed.',
        ]);
    }

    /**
     * Replace a tutor subject's grade entries with the given set of grades.
     *
     * @param  array<int, int>  $gradeIds
     */
    private function syncGrades(TutorSubject $tutorSubject, array $gradeIds): void
    {
        $tutorSubject->tutorSubjectGrades()->delete();
        $tutorSubject->tutorSubjectGrades()->createMany(
            collect($gradeIds)->map(fn ($gradeId) => ['grade_id' => $gradeId])->all(),
        );
    }
}
