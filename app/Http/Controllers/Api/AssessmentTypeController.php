<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssessmentTypeResource;
use App\Models\AssessmentType;
use Illuminate\Http\JsonResponse;

class AssessmentTypeController extends Controller
{
    /**
     * Return every active assessment type in the platform's master list.
     */
    public function index(): JsonResponse
    {
        $types = AssessmentType::query()->where('is_active', true)->orderBy('name')->get();

        return response()->json([
            'types' => AssessmentTypeResource::collection($types),
        ]);
    }
}
