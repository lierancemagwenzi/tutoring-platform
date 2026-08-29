<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceCategoryResource;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;

class ServiceCategoryController extends Controller
{
    /**
     * Return every active service category in the platform's master list.
     */
    public function index(): JsonResponse
    {
        $categories = ServiceCategory::query()->where('is_active', true)->orderBy('name')->get();

        return response()->json([
            'categories' => ServiceCategoryResource::collection($categories),
        ]);
    }
}
