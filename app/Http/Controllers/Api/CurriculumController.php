<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurriculumResource;
use App\Models\Curriculum;
use Illuminate\Http\JsonResponse;

class CurriculumController extends Controller
{
    /**
     * Return every active curriculum in the platform's master list.
     */
    public function index(): JsonResponse
    {
        $curricula = Curriculum::query()->where('is_active', true)->orderBy('name')->get();

        return response()->json([
            'curricula' => CurriculumResource::collection($curricula),
        ]);
    }
}
