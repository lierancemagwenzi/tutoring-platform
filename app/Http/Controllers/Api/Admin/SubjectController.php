<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SubjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubjectRequest;
use App\Http\Requests\Admin\UpdateSubjectRequest;
use App\Http\Resources\Admin\SubjectResource;
use App\Models\Subject;
use App\Services\Admin\SubjectManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function __construct(private readonly SubjectManagementService $subjects) {}

    public function index(Request $request): JsonResponse
    {
        $subjects = $this->subjects->list(
            $request->only(['search', 'status']),
            (int) $request->integer('per_page', 15),
        );

        return response()->json([
            'subjects' => SubjectResource::collection($subjects->items()),
            'meta' => [
                'current_page' => $subjects->currentPage(),
                'last_page' => $subjects->lastPage(),
                'per_page' => $subjects->perPage(),
                'total' => $subjects->total(),
            ],
        ]);
    }

    public function show(Subject $subject): JsonResponse
    {
        return response()->json([
            'subject' => $this->subjects->overview($subject),
        ]);
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $subject = $this->subjects->create($request->validated());

        return response()->json(['subject' => new SubjectResource($subject)], 201);
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): JsonResponse
    {
        $subject = $this->subjects->update($subject, $request->validated());

        return response()->json(['subject' => new SubjectResource($subject)]);
    }

    public function activate(Request $request, Subject $subject): JsonResponse
    {
        $subject = $this->subjects->setStatus($subject, SubjectStatus::Active, $request->user());

        return response()->json(['subject' => new SubjectResource($subject)]);
    }

    public function deactivate(Request $request, Subject $subject): JsonResponse
    {
        $subject = $this->subjects->setStatus($subject, SubjectStatus::Inactive, $request->user());

        return response()->json(['subject' => new SubjectResource($subject)]);
    }

    public function archive(Request $request, Subject $subject): JsonResponse
    {
        $subject = $this->subjects->setStatus($subject, SubjectStatus::Archived, $request->user());

        return response()->json(['subject' => new SubjectResource($subject)]);
    }
}
