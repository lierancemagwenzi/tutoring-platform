<?php

namespace App\Services\Admin;

use App\Models\CourseCertificate;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Read-only admin visibility into issued certificates. Certificate
 * generation/download is reused unmodified from CertificateService — this
 * only lists and looks up existing rows, never regenerates one.
 */
class AdminCertificateManagementService
{
    /**
     * @param  array{search?: string}  $filters
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = CourseCertificate::query()->with(['enrollment.student', 'enrollment.course.tutorProfile']);

        if (! empty($filters['search'])) {
            $query->where('certificate_number', 'like', '%'.$filters['search'].'%');
        }

        return $query->latest('issued_at')->paginate($perPage);
    }
}
