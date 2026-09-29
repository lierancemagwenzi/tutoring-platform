<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGradeRequest;
use App\Http\Requests\Admin\UpdateGradeRequest;
use App\Http\Resources\Admin\GradeResource;
use App\Models\Grade;
use App\Services\Admin\AdminActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Grades are never deleted — services and courses reference them.
 * Deactivating one hides it from every picker and filter (which only
 * list active grades) while existing records keep pointing at it.
 */
class GradeController extends Controller
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function index(): JsonResponse
    {
        $grades = Grade::query()->withCount(['services', 'selfPacedCourses'])->orderBy('level')->get();

        return response()->json(['grades' => GradeResource::collection($grades)]);
    }

    public function store(StoreGradeRequest $request): JsonResponse
    {
        $grade = Grade::create([...$request->validated(), 'is_active' => true]);

        $this->logger->log($request->user(), 'grade.created', $grade, "Grade \"{$grade->name}\" created.");

        return response()->json(['grade' => new GradeResource($grade)], 201);
    }

    public function update(UpdateGradeRequest $request, Grade $grade): JsonResponse
    {
        $grade->update($request->validated());

        $this->logger->log($request->user(), 'grade.updated', $grade, "Grade \"{$grade->name}\" updated.");

        return response()->json(['grade' => new GradeResource($grade)]);
    }

    public function activate(Request $request, Grade $grade): JsonResponse
    {
        return $this->setActive($request, $grade, true);
    }

    public function deactivate(Request $request, Grade $grade): JsonResponse
    {
        return $this->setActive($request, $grade, false);
    }

    private function setActive(Request $request, Grade $grade, bool $active): JsonResponse
    {
        $grade->update(['is_active' => $active]);

        $state = $active ? 'activated' : 'deactivated';
        $this->logger->log($request->user(), "grade.{$state}", $grade, "Grade \"{$grade->name}\" {$state}.");

        return response()->json(['grade' => new GradeResource($grade)]);
    }
}
