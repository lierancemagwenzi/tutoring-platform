<?php

namespace App\Http\Controllers\Api\Tutor\SelfPaced\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tutor\SelfPaced\Analytics\TutorCourseCertificateResource;
use App\Models\CourseCertificate;
use App\Models\SelfPacedCourse;
use App\Services\LearnerProgress\CertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CourseCertificatesController extends Controller
{
    /**
     * Every certificate issued for this course, paginated.
     */
    public function index(Request $request, SelfPacedCourse $selfPacedCourse): JsonResponse
    {
        $this->authorize('view', $selfPacedCourse);

        $certificates = CourseCertificate::query()
            ->whereHas('enrollment', fn ($query) => $query->where('self_paced_course_id', $selfPacedCourse->id))
            ->with('enrollment')
            ->latest('issued_at')
            ->paginate($request->integer('per_page') ?: 15);

        return response()->json([
            'certificates' => TutorCourseCertificateResource::collection($certificates->items()),
            'meta' => [
                'current_page' => $certificates->currentPage(),
                'last_page' => $certificates->lastPage(),
                'per_page' => $certificates->perPage(),
                'total' => $certificates->total(),
            ],
        ]);
    }

    /**
     * Stream the already-generated certificate PDF — never regenerated,
     * reusing CertificateService::download() exactly as the student-facing
     * controller does, just gated by tutor ownership instead of student
     * ownership.
     */
    public function download(SelfPacedCourse $selfPacedCourse, CourseCertificate $certificate, CertificateService $certificates): Response
    {
        $this->authorize('view', $selfPacedCourse);
        abort_unless($certificate->enrollment->self_paced_course_id === $selfPacedCourse->id, 404);

        return $certificates->download($certificate);
    }
}
