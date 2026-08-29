<?php

namespace App\Services\SelfPaced\Analytics;

use App\Enums\EnrollmentStatus;
use App\Models\CourseCertificate;
use App\Models\Enrollment;
use App\Models\ModuleProgress;
use App\Models\SelfPacedAssessmentAttempt;
use App\Models\SelfPacedCourse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * The paginated "Enrolled Students" list — and, filtered to
 * status=completed, the "Completed Students" section too, since they're the
 * same table with a different filter. Every per-row computed column (progress,
 * current chapter, average score, certificate) is bulk-fetched for the page
 * of enrollment ids being displayed, never looped per row — the per-row
 * technique mirrors CourseAnalyticsService's course-wide version, just
 * scoped to one page instead of the whole course.
 */
class EnrollmentAnalyticsService
{
    /**
     * A student with no activity in this many days is flagged inactive —
     * matches the threshold StudentWorkService already uses for the
     * Tutor-Led side's "no recent activity" flag.
     */
    private const INACTIVITY_THRESHOLD_DAYS = 14;

    /**
     * @param  array{status?: string, certificate_issued?: bool, search?: string, inactive_only?: bool}  $filters
     */
    public function listForCourse(SelfPacedCourse $course, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $course->loadMissing('modules.assessments');
        $totalModules = $course->modules->count();
        $assessmentIds = $course->modules->flatMap(fn ($module) => $module->assessments->pluck('id'));

        $query = Enrollment::query()
            ->where('self_paced_course_id', $course->id)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->with('student');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            // Matches first_name/last_name individually rather than a
            // concatenated full name — string concatenation syntax isn't
            // portable across the MySQL (dev/prod) and SQLite (test) drivers
            // this app runs on.
            $query->whereHas('student', fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%"));
        }

        if (! empty($filters['certificate_issued'])) {
            $query->whereHas('certificate');
        }

        if (! empty($filters['inactive_only'])) {
            $query->where(function ($q) {
                $q->whereNull('last_accessed_at')
                    ->orWhere('last_accessed_at', '<', Carbon::now()->subDays(self::INACTIVITY_THRESHOLD_DAYS));
            });
        }

        // "not_started"/"in_progress" both mean status=Active, differing
        // only by whether any module has been completed yet — pushed into
        // the query itself (via the moduleProgress relation) rather than
        // filtered after paginate(), so total()/last_page() stay correct.
        match ($filters['status'] ?? null) {
            'completed' => $query->where('status', EnrollmentStatus::Completed),
            'not_started' => $query->where('status', EnrollmentStatus::Active)
                ->whereDoesntHave('moduleProgress', fn ($q) => $q->whereNotNull('completed_at')),
            'in_progress' => $query->where('status', EnrollmentStatus::Active)
                ->whereHas('moduleProgress', fn ($q) => $q->whereNotNull('completed_at')),
            default => null,
        };

        $enrollments = $query->latest('enrolled_at')->paginate($perPage);
        $ids = collect($enrollments->items())->pluck('id');
        $studentIds = collect($enrollments->items())->pluck('student_id');

        $completedModulesByEnrollment = ModuleProgress::query()
            ->whereIn('enrollment_id', $ids)
            ->whereNotNull('completed_at')
            ->get(['enrollment_id', 'self_paced_module_id'])
            ->groupBy('enrollment_id');

        $averageScoresByStudent = SelfPacedAssessmentAttempt::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('self_paced_assessment_id', $assessmentIds)
            ->whereNotNull('percentage')
            ->selectRaw('student_id, avg(percentage) as avg_score')
            ->groupBy('student_id')
            ->pluck('avg_score', 'student_id');

        $certificatesByEnrollment = CourseCertificate::query()
            ->whereIn('enrollment_id', $ids)
            ->get(['id', 'enrollment_id', 'certificate_number'])
            ->keyBy('enrollment_id');

        $orderedModuleIds = $course->modules->pluck('id');
        $moduleTitlesById = $course->modules->pluck('title', 'id');

        $rows = collect($enrollments->items())->map(function (Enrollment $enrollment) use (
            $totalModules, $completedModulesByEnrollment, $averageScoresByStudent, $certificatesByEnrollment, $orderedModuleIds, $moduleTitlesById,
        ) {
            $completedModuleIds = ($completedModulesByEnrollment[$enrollment->id] ?? collect())->pluck('self_paced_module_id');
            $completedCount = $completedModuleIds->count();
            $currentModuleId = $orderedModuleIds->first(fn ($id) => ! $completedModuleIds->contains($id));
            $certificate = $certificatesByEnrollment[$enrollment->id] ?? null;

            $courseStatus = match (true) {
                $enrollment->status === EnrollmentStatus::Completed => 'completed',
                $completedCount > 0 => 'in_progress',
                default => 'not_started',
            };

            $enrollment->setAttribute('analytics', [
                'progress_percentage' => $totalModules > 0 ? round($completedCount / $totalModules * 100, 2) : 0.0,
                'current_module_id' => $currentModuleId,
                'current_module_title' => $currentModuleId !== null ? ($moduleTitlesById[$currentModuleId] ?? null) : null,
                'course_status' => $courseStatus,
                'average_assessment_score' => isset($averageScoresByStudent[$enrollment->student_id])
                    ? round((float) $averageScoresByStudent[$enrollment->student_id], 2)
                    : null,
                'certificate_number' => $certificate?->certificate_number,
                'has_certificate' => $certificate !== null,
                'days_since_last_activity' => $enrollment->last_accessed_at ? (int) $enrollment->last_accessed_at->diffInDays(Carbon::now()) : null,
                'is_inactive' => $enrollment->last_accessed_at === null
                    || $enrollment->last_accessed_at->lt(Carbon::now()->subDays(self::INACTIVITY_THRESHOLD_DAYS)),
            ]);

            return $enrollment;
        });

        return new LengthAwarePaginator(
            $rows,
            $enrollments->total(),
            $enrollments->perPage(),
            $enrollments->currentPage(),
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }
}
