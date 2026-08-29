<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CertificateManagementResource;
use App\Models\CourseCertificate;
use App\Services\Admin\AdminCertificateManagementService;
use App\Services\LearnerProgress\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CertificateManagementController extends Controller
{
    public function __construct(
        private readonly AdminCertificateManagementService $certificates,
        private readonly CertificateService $certificateService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $certificates = $this->certificates->list($request->only(['search']), (int) $request->integer('per_page', 15));

        return response()->json([
            'certificates' => CertificateManagementResource::collection($certificates->items()),
            'meta' => [
                'current_page' => $certificates->currentPage(),
                'last_page' => $certificates->lastPage(),
                'per_page' => $certificates->perPage(),
                'total' => $certificates->total(),
            ],
        ]);
    }

    public function show(CourseCertificate $certificate): JsonResponse
    {
        return response()->json(['certificate' => new CertificateManagementResource($certificate)]);
    }

    public function download(CourseCertificate $certificate): Response
    {
        return $this->certificateService->download($certificate);
    }

    /**
     * Confirms a certificate's authenticity by number — the admin-facing
     * counterpart to the certificate's reserved verification_uuid (no
     * public verification endpoint exists yet).
     */
    public function verify(Request $request): JsonResponse
    {
        $certificate = CourseCertificate::where('certificate_number', $request->query('certificate_number'))->first();

        if (! $certificate) {
            return response()->json(['valid' => false]);
        }

        return response()->json([
            'valid' => true,
            'certificate' => new CertificateManagementResource($certificate),
        ]);
    }
}
