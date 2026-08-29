<?php

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\LearnerProgress\CourseProgressService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The student-facing admin management view — read-only. Course progress
 * is reused directly from CourseProgressService (bounded to one student's
 * handful of enrollments), matching how StudentProgressService reuses it
 * on the tutor analytics side rather than re-deriving the numbers here.
 */
class AdminStudentManagementService
{
    public function __construct(private readonly CourseProgressService $courseProgress) {}

    /**
     * @param  array{search?: string}  $filters
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()->where('role', UserRole::Student)
            ->withCount(['enrollments', 'bookings']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(User $student): array
    {
        $student->loadMissing([
            'enrollments.course', 'enrollments.certificate',
            'bookings.service', 'bookings.tutorProfile',
        ]);

        return [
            'student' => [
                'id' => $student->id,
                'name' => trim("{$student->first_name} {$student->last_name}"),
                'email' => $student->email,
                'email_verified' => $student->hasVerifiedEmail(),
                'disabled' => $student->disabled_at !== null,
                'registered_at' => $student->created_at->toIso8601String(),
            ],
            'enrollments' => $student->enrollments->map(fn ($enrollment) => [
                'id' => $enrollment->id,
                'course_title' => $enrollment->course?->title,
                'status' => $enrollment->status->value,
                'progress' => $this->courseProgress->summarize($enrollment),
                'certificate_issued' => $enrollment->certificate !== null,
            ])->values(),
            'bookings' => $student->bookings->map(fn ($booking) => [
                'id' => $booking->id,
                'service_title' => $booking->service?->title,
                'tutor' => $booking->tutorProfile?->display_name,
                'status' => $booking->status->value,
                'date' => $booking->date?->toDateString(),
            ])->values(),
            'certificates' => $student->enrollments->pluck('certificate')->filter()->map(fn ($certificate) => [
                'id' => $certificate->id,
                'certificate_number' => $certificate->certificate_number,
                'course_title' => $certificate->course_title,
                'issued_at' => $certificate->issued_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
