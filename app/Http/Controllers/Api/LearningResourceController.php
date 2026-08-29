<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LearningResourceResource;
use App\Models\LearningResource;
use Illuminate\Http\JsonResponse;

class LearningResourceController extends Controller
{
    /**
     * Return every active learning resource in the platform's master list.
     */
    public function index(): JsonResponse
    {
        $resources = LearningResource::query()->where('is_active', true)->orderBy('name')->get();

        return response()->json([
            'resources' => LearningResourceResource::collection($resources),
        ]);
    }
}
