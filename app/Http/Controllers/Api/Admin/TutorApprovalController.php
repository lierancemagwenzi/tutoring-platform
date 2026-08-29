<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveTutorRequest;
use App\Http\Requests\Admin\RejectTutorRequest;
use App\Http\Requests\Admin\RequestTutorChangesRequest;
use App\Http\Resources\Admin\TutorApprovalResource;
use App\Models\User;
use App\Services\Admin\TutorApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorApprovalController extends Controller
{
    public function __construct(private readonly TutorApprovalService $approvals) {}

    public function index(Request $request): JsonResponse
    {
        $pending = $this->approvals->pending((int) $request->integer('per_page', 15));

        return response()->json([
            'tutors' => TutorApprovalResource::collection($pending->items()),
            'meta' => [
                'current_page' => $pending->currentPage(),
                'last_page' => $pending->lastPage(),
                'per_page' => $pending->perPage(),
                'total' => $pending->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        abort_unless($user->tutorProfile, 404);

        return response()->json(['tutor' => $this->approvals->detail($user)]);
    }

    public function approve(ApproveTutorRequest $request, User $user): JsonResponse
    {
        abort_unless($user->tutorProfile, 404);

        $tutor = $this->approvals->approve($user, $request->user());

        return response()->json(['tutor' => new TutorApprovalResource($tutor->load('tutorProfile'))]);
    }

    public function reject(RejectTutorRequest $request, User $user): JsonResponse
    {
        abort_unless($user->tutorProfile, 404);

        $tutor = $this->approvals->reject($user, $request->user(), $request->validated('reason'));

        return response()->json(['tutor' => new TutorApprovalResource($tutor->load('tutorProfile'))]);
    }

    public function requestChanges(RequestTutorChangesRequest $request, User $user): JsonResponse
    {
        abort_unless($user->tutorProfile, 404);

        $tutor = $this->approvals->requestChanges($user, $request->user(), $request->validated('note'));

        return response()->json(['tutor' => new TutorApprovalResource($tutor->load('tutorProfile'))]);
    }
}
