<?php

namespace App\Http\Controllers\Api\Tutor\SelfPaced;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\SelfPaced\ManageSelfPacedModuleRequest;
use App\Http\Requests\Tutor\SelfPaced\ReorderSelfPacedModulesRequest;
use App\Http\Requests\Tutor\SelfPaced\StoreSelfPacedModuleRequest;
use App\Http\Requests\Tutor\SelfPaced\UpdateSelfPacedModuleRequest;
use App\Http\Resources\SelfPacedModuleResource;
use App\Models\SelfPacedCourse;
use App\Models\SelfPacedModule;
use App\Services\SelfPaced\SelfPacedModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfPacedModuleController extends Controller
{
    public function __construct(protected SelfPacedModuleService $modules) {}

    /**
     * List a course's modules, with their content, in order.
     */
    public function index(Request $request, SelfPacedCourse $selfPacedCourse): JsonResponse
    {
        abort_unless($selfPacedCourse->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        return response()->json([
            'modules' => SelfPacedModuleResource::collection(
                $selfPacedCourse->modules()->with(['activities.attachments', 'assessments'])->get(),
            ),
        ]);
    }

    /**
     * Add a new module to a course.
     */
    public function store(StoreSelfPacedModuleRequest $request, SelfPacedCourse $selfPacedCourse): JsonResponse
    {
        $module = $this->modules->create($selfPacedCourse, $request->validated());

        return response()->json(['module' => new SelfPacedModuleResource($module)], 201);
    }

    /**
     * Update a module's title/description/completion configuration.
     */
    public function update(UpdateSelfPacedModuleRequest $request, SelfPacedModule $selfPacedModule): JsonResponse
    {
        $module = $this->modules->update($selfPacedModule, $request->validated());

        return response()->json(['module' => new SelfPacedModuleResource($module)]);
    }

    /**
     * Delete a module and its content.
     */
    public function destroy(ManageSelfPacedModuleRequest $request, SelfPacedModule $selfPacedModule): JsonResponse
    {
        $this->modules->delete($selfPacedModule);

        return response()->json(['message' => 'Module deleted.']);
    }

    /**
     * Persist the drag-and-drop order of a course's modules.
     */
    public function reorder(ReorderSelfPacedModulesRequest $request, SelfPacedCourse $selfPacedCourse): JsonResponse
    {
        $this->modules->reorder($selfPacedCourse, $request->validated('module_ids'));

        return response()->json([
            'modules' => SelfPacedModuleResource::collection($selfPacedCourse->modules()->get()),
        ]);
    }
}
