<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Resources\TutorApplicationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorApplicationController extends Controller
{
    /**
     * Return the current tutor's application progress.
     */
    public function show(Request $request): JsonResponse
    {
        $tutorProfile = $request->user()->tutorProfile()->with(['qualifications', 'documents'])->firstOrFail();

        return response()->json([
            'application' => new TutorApplicationResource($tutorProfile),
        ]);
    }
}
