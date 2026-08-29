<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Enums\ServiceVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\PauseServiceRequest;
use App\Http\Requests\Tutor\PublishServiceRequest;
use App\Http\Requests\Tutor\StoreServiceRequest;
use App\Http\Requests\Tutor\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * The relations eager-loaded on every service response.
     *
     * @var list<string>
     */
    private const WITH = ['subject', 'grade', 'category', 'sessionFormat', 'learningResources', 'assessmentTypes', 'curricula'];

    /**
     * Return the logged in tutor's services.
     */
    public function index(Request $request): JsonResponse
    {
        $services = $request->user()->tutorProfile
            ->services()
            ->with(self::WITH)
            ->latest()
            ->get();

        return response()->json([
            'services' => ServiceResource::collection($services),
        ]);
    }

    /**
     * Create a new service for the logged in tutor.
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $request->user()->tutorProfile
            ->services()
            ->create([
                ...$request->safe()->except(['learning_resource_ids', 'assessment_type_ids', 'curriculum_ids', 'visibility']),
                'visibility' => $request->validated('visibility') ?? ServiceVisibility::Draft->value,
            ]);

        $this->syncResources($service, $request);

        return response()->json([
            'service' => new ServiceResource($service->load(self::WITH)),
        ], 201);
    }

    /**
     * Show a single service belonging to the logged in tutor.
     */
    public function show(Request $request, Service $service): JsonResponse
    {
        abort_unless($service->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        return response()->json([
            'service' => new ServiceResource($service->load(self::WITH)),
        ]);
    }

    /**
     * Update an existing service belonging to the logged in tutor.
     */
    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $service->update(
            $request->safe()->except(['learning_resource_ids', 'assessment_type_ids', 'curriculum_ids']),
        );

        $this->syncResources($service, $request);

        return response()->json([
            'service' => new ServiceResource($service->load(self::WITH)),
        ]);
    }

    /**
     * Publish a service, making it visible as an active offering.
     */
    public function publish(PublishServiceRequest $request, Service $service): JsonResponse
    {
        $service->update(['visibility' => ServiceVisibility::Published]);

        return response()->json([
            'service' => new ServiceResource($service->load(self::WITH)),
        ]);
    }

    /**
     * Pause a service, temporarily hiding it as an active offering.
     */
    public function pause(PauseServiceRequest $request, Service $service): JsonResponse
    {
        $service->update(['visibility' => ServiceVisibility::Paused]);

        return response()->json([
            'service' => new ServiceResource($service->load(self::WITH)),
        ]);
    }

    /**
     * Sync the many-to-many resource lists (learning resources, assessment types, curricula) for a service.
     */
    private function syncResources(Service $service, StoreServiceRequest|UpdateServiceRequest $request): void
    {
        $service->learningResources()->sync($request->validated('learning_resource_ids') ?? []);
        $service->assessmentTypes()->sync($request->validated('assessment_type_ids') ?? []);
        $service->curricula()->sync($request->validated('curriculum_ids') ?? []);
    }
}
