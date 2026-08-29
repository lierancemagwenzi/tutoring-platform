<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SessionFormatResource;
use App\Models\SessionFormat;
use Illuminate\Http\JsonResponse;

class SessionFormatController extends Controller
{
    /**
     * Return every active session format in the platform's master list.
     */
    public function index(): JsonResponse
    {
        $formats = SessionFormat::query()->where('is_active', true)->orderBy('name')->get();

        return response()->json([
            'formats' => SessionFormatResource::collection($formats),
        ]);
    }
}
