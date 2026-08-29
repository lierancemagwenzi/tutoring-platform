<?php

namespace App\Http\Controllers\Api\Tutor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\IdentityDocumentRequest;
use App\Http\Resources\TutorApplicationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class IdentityDocumentController extends Controller
{
    /**
     * Upload or replace the tutor's government ID document.
     */
    public function __invoke(IdentityDocumentRequest $request): JsonResponse
    {
        $tutorProfile = $request->user()->tutorProfile;

        if ($tutorProfile->government_id_path) {
            Storage::disk('public')->delete($tutorProfile->government_id_path);
        }

        $file = $request->file('file');

        $tutorProfile->update([
            'government_id_path' => $file->store('identity-documents', 'public'),
            'government_id_name' => $file->getClientOriginalName(),
            'onboarding_step' => max($tutorProfile->onboarding_step, 5),
        ]);

        return response()->json([
            'application' => new TutorApplicationResource($tutorProfile->fresh(['qualifications', 'documents'])),
        ]);
    }
}
