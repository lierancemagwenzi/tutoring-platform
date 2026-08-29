<?php

namespace App\Http\Controllers\Api\Tutor\SelfPaced;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\SelfPaced\ManageSelfPacedActivityRequest;
use App\Http\Requests\Tutor\SelfPaced\StoreSelfPacedActivityRequest;
use App\Http\Requests\Tutor\SelfPaced\UpdateSelfPacedActivityRequest;
use App\Http\Resources\SelfPacedActivityResource;
use App\Models\SelfPacedActivity;
use App\Models\SelfPacedModule;
use App\Services\SelfPaced\SelfPacedActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelfPacedActivityController extends Controller
{
    public function __construct(protected SelfPacedActivityService $activities) {}

    /**
     * List a module's Learning Activities, in order.
     */
    public function index(Request $request, SelfPacedModule $selfPacedModule): JsonResponse
    {
        abort_unless($selfPacedModule->course->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        return response()->json([
            'activities' => SelfPacedActivityResource::collection($selfPacedModule->activities()->with('attachments')->get()),
        ]);
    }

    /**
     * Add a new Learning Activity to a module.
     */
    public function store(StoreSelfPacedActivityRequest $request, SelfPacedModule $selfPacedModule): JsonResponse
    {
        $activity = $this->activities->create($selfPacedModule, $request->validated());

        return response()->json(['activity' => new SelfPacedActivityResource($activity->load('attachments'))], 201);
    }

    /**
     * Update a Learning Activity's title/description/required flag/content.
     */
    public function update(UpdateSelfPacedActivityRequest $request, SelfPacedActivity $selfPacedActivity): JsonResponse
    {
        $activity = $this->activities->update($selfPacedActivity, $request->validated());

        return response()->json(['activity' => new SelfPacedActivityResource($activity->load('attachments'))]);
    }

    /**
     * Delete a Learning Activity and its attachments.
     */
    public function destroy(ManageSelfPacedActivityRequest $request, SelfPacedActivity $selfPacedActivity): JsonResponse
    {
        $this->activities->delete($selfPacedActivity);

        return response()->json(['message' => 'Activity deleted.']);
    }
}
