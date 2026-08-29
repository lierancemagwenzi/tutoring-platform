<?php

namespace App\Http\Controllers\Api\Student\SelfPaced;

use App\Http\Controllers\Controller;
use App\Http\Resources\Student\SelfPaced\CourseCertificateResource;
use App\Models\CourseCertificate;
use App\Services\LearnerProgress\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CourseCertificateController extends Controller
{
    /**
     * Every certificate the logged in student has earned, across all courses.
     */
    public function index(Request $request): JsonResponse
    {
        $certificates = CourseCertificate::query()
            ->whereHas('enrollment', fn ($query) => $query->where('student_id', $request->user()->id))
            ->with('enrollment')
            ->latest('issued_at')
            ->get();

        return response()->json([
            'certificates' => CourseCertificateResource::collection($certificates),
        ]);
    }

    /**
     * Stream the certificate PDF, restricted to the student who earned it.
     */
    public function download(Request $request, CourseCertificate $certificate, CertificateService $certificates): Response
    {
        abort_unless($certificate->enrollment->student_id === $request->user()->id, 403);

        return $certificates->download($certificate);
    }
}
