<?php

namespace App\Services\LearnerProgress;

use App\Models\CourseCertificate;
use App\Models\Enrollment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issues and serves course-completion certificates. Generated exactly once
 * per enrollment and rendered to a permanently-stored PDF at that moment —
 * never re-rendered from current (possibly since-edited) course/tutor data
 * on every download, which is what keeps an issued certificate immutable.
 */
class CertificateService
{
    /**
     * Idempotent: returns the existing certificate if this enrollment
     * already has one.
     */
    public function generate(Enrollment $enrollment): CourseCertificate
    {
        $existing = CourseCertificate::where('enrollment_id', $enrollment->id)->first();

        if ($existing) {
            return $existing;
        }

        $enrollment->loadMissing(['student', 'course.tutorProfile']);

        try {
            $certificate = CourseCertificate::create([
                'enrollment_id' => $enrollment->id,
                'certificate_number' => $this->generateCertificateNumber(),
                'verification_uuid' => (string) Str::uuid(),
                'student_name' => trim("{$enrollment->student->first_name} {$enrollment->student->last_name}"),
                'course_title' => $enrollment->course->title,
                'tutor_name' => $enrollment->course->tutorProfile?->display_name ?? 'Instructor',
                'issued_at' => now(),
            ]);
        } catch (QueryException) {
            // A concurrent request already issued this enrollment's certificate.
            return CourseCertificate::where('enrollment_id', $enrollment->id)->firstOrFail();
        }

        $pdfPath = "certificates/{$certificate->certificate_number}.pdf";
        $pdf = Pdf::loadView('certificates.course-completion', ['certificate' => $certificate]);
        Storage::disk('local')->put($pdfPath, $pdf->output());

        $certificate->update(['pdf_path' => $pdfPath]);

        return $certificate->fresh();
    }

    /**
     * Stream the stored PDF back to the requesting (already
     * ownership-checked) student.
     */
    public function download(CourseCertificate $certificate): Response
    {
        abort_unless(
            $certificate->pdf_path && Storage::disk('local')->exists($certificate->pdf_path),
            404,
            'This certificate is not available for download.',
        );

        return Storage::disk('local')->download($certificate->pdf_path, "{$certificate->certificate_number}.pdf");
    }

    /**
     * A short random suffix rather than a sequential counter — avoids any
     * race condition between two certificates issued in the same request
     * window without needing a locking read before insert.
     */
    private function generateCertificateNumber(): string
    {
        return sprintf('CERT-%d-%s', now()->year, strtoupper(Str::random(8)));
    }
}
