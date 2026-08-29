<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ReplaceTutorDocumentRequest;
use App\Http\Requests\Tutor\StoreTutorDocumentRequest;
use App\Http\Resources\TutorDocumentResource;
use App\Models\TutorDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TutorDocumentController extends Controller
{
    /**
     * Upload a new supporting document.
     */
    public function store(StoreTutorDocumentRequest $request): JsonResponse
    {
        $tutorProfile = $request->user()->tutorProfile;
        $file = $request->file('file');

        $document = $tutorProfile->documents()->create([
            'type' => $request->validated('type'),
            'path' => $file->store('supporting-documents', 'public'),
            'original_name' => $file->getClientOriginalName(),
        ]);

        $tutorProfile->update([
            'onboarding_step' => max($tutorProfile->onboarding_step, 6),
        ]);

        return response()->json([
            'document' => new TutorDocumentResource($document),
        ], 201);
    }

    /**
     * Replace the file behind an existing supporting document.
     */
    public function replace(ReplaceTutorDocumentRequest $request, TutorDocument $document): JsonResponse
    {
        Storage::disk('public')->delete($document->path);

        $file = $request->file('file');

        $document->update([
            'path' => $file->store('supporting-documents', 'public'),
            'original_name' => $file->getClientOriginalName(),
        ]);

        return response()->json([
            'document' => new TutorDocumentResource($document),
        ]);
    }

    /**
     * Delete a supporting document.
     */
    public function destroy(Request $request, TutorDocument $document): JsonResponse
    {
        abort_unless($document->tutor_profile_id === $request->user()->tutorProfile?->id, 403);

        Storage::disk('public')->delete($document->path);
        $document->delete();

        return response()->json([
            'message' => 'Document deleted.',
        ]);
    }
}
