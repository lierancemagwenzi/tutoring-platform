<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GradeResource;
use App\Models\Grade;
use Illuminate\Http\JsonResponse;

class GradeController extends Controller
{
    /**
     * Return every active grade in the platform's master list.
     */
    public function index(): JsonResponse
    {
        $grades = Grade::query()->where('is_active', true)->orderBy('level')->get();

        return response()->json([
            'grades' => GradeResource::collection($grades),
        ]);
    }
}
