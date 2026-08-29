<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TutorManagementResource;
use App\Models\TutorProfile;
use App\Services\Admin\AdminTutorManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorManagementController extends Controller
{
    public function __construct(private readonly AdminTutorManagementService $tutors) {}

    public function index(Request $request): JsonResponse
    {
        $tutors = $this->tutors->list($request->only(['search']), (int) $request->integer('per_page', 15));

        return response()->json([
            'tutors' => TutorManagementResource::collection($tutors->items()),
            'meta' => [
                'current_page' => $tutors->currentPage(),
                'last_page' => $tutors->lastPage(),
                'per_page' => $tutors->perPage(),
                'total' => $tutors->total(),
            ],
        ]);
    }

    public function show(TutorProfile $tutorProfile): JsonResponse
    {
        return response()->json(['tutor' => $this->tutors->detail($tutorProfile)]);
    }
}
