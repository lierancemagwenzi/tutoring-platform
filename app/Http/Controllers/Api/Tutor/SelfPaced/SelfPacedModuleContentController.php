<?php

namespace App\Http\Controllers\Api\Tutor\SelfPaced;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\SelfPaced\ReorderSelfPacedModuleContentRequest;
use App\Http\Resources\SelfPacedModuleResource;
use App\Models\SelfPacedModule;
use App\Services\SelfPaced\SelfPacedModuleContentService;
use Illuminate\Http\JsonResponse;

class SelfPacedModuleContentController extends Controller
{
    public function __construct(protected SelfPacedModuleContentService $moduleContent) {}

    /**
     * Persist the drag-and-drop order of a module's combined Activities and
     * Assessments (see SelfPacedModule::orderedContent()).
     */
    public function reorder(ReorderSelfPacedModuleContentRequest $request, SelfPacedModule $selfPacedModule): JsonResponse
    {
        $this->moduleContent->reorder($selfPacedModule, $request->validated('items'));

        return response()->json([
            'module' => new SelfPacedModuleResource($selfPacedModule->fresh(['activities.attachments', 'assessments'])),
        ]);
    }
}
