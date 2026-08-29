<?php

namespace App\Http\Controllers\Api;

use App\Enums\TutorSubjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    /**
     * Return every active subject in the platform's master list.
     */
    public function index(): JsonResponse
    {
        $subjects = Subject::query()->where('is_active', true)->orderBy('name')->get();

        return response()->json([
            'subjects' => SubjectResource::collection($subjects),
        ]);
    }

    /**
     * Return only the active subjects the requesting tutor is approved to
     * teach — used to scope the subject selector when creating a Service or
     * SelfPacedCourse, so a tutor never sees the full platform catalogue,
     * only what they may actually offer.
     */
    public function approvedForTutor(Request $request): JsonResponse
    {
        $tutor = $request->user()->tutorProfile;

        $subjects = Subject::query()
            ->active()
            ->whereHas('tutorSubjects', fn ($q) => $q->where('tutor_profile_id', $tutor?->id)->where('status', TutorSubjectStatus::Approved))
            ->orderBy('name')
            ->get();

        return response()->json([
            'subjects' => SubjectResource::collection($subjects),
        ]);
    }
}
