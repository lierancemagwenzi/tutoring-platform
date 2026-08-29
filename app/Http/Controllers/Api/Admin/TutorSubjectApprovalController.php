<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectTutorSubjectRequest;
use App\Http\Requests\Admin\SuspendTutorSubjectRequest;
use App\Http\Resources\Admin\TutorSubjectRequestResource;
use App\Models\TutorSubject;
use App\Services\Admin\TutorSubjectApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorSubjectApprovalController extends Controller
{
    public function __construct(private readonly TutorSubjectApprovalService $approvals) {}

    public function index(Request $request): JsonResponse
    {
        $pending = $this->approvals->pending((int) $request->integer('per_page', 15));

        return response()->json([
            'requests' => TutorSubjectRequestResource::collection($pending->items()),
            'meta' => [
                'current_page' => $pending->currentPage(),
                'last_page' => $pending->lastPage(),
                'per_page' => $pending->perPage(),
                'total' => $pending->total(),
            ],
        ]);
    }

    public function approve(Request $request, TutorSubject $tutorSubject): JsonResponse
    {
        $tutorSubject = $this->approvals->approve($tutorSubject, $request->user());

        return response()->json(['request' => new TutorSubjectRequestResource($tutorSubject->load(['tutorProfile.user', 'subject']))]);
    }

    public function reject(RejectTutorSubjectRequest $request, TutorSubject $tutorSubject): JsonResponse
    {
        $tutorSubject = $this->approvals->reject($tutorSubject, $request->user(), $request->validated('reason'));

        return response()->json(['request' => new TutorSubjectRequestResource($tutorSubject->load(['tutorProfile.user', 'subject']))]);
    }

    public function suspend(SuspendTutorSubjectRequest $request, TutorSubject $tutorSubject): JsonResponse
    {
        $tutorSubject = $this->approvals->suspend($tutorSubject, $request->user(), $request->validated('reason'));

        return response()->json(['request' => new TutorSubjectRequestResource($tutorSubject->load(['tutorProfile.user', 'subject']))]);
    }
}
