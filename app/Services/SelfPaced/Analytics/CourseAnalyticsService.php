<?php

namespace App\Services\SelfPaced\Analytics;

use App\Enums\EnrollmentStatus;
use App\Models\CourseCertificate;
use App\Models\Enrollment;
use App\Models\ModuleProgress;
use App\Models\SelfPacedAssessmentAttempt;
use App\Models\SelfPacedCourse;

/**
 * Course-level analytics for the tutor's dashboard — everything computed
 * via grouped aggregate queries over the existing learner-progress tables
 * (Enrollment/ModuleProgress/SelfPacedAssessmentAttempt/CourseCertificate),
 * never by looping CourseProgressService::summarize() per student (that
 * service is for a single enrollment's detail view, not a course-wide
 * rollup — looping it here would be an N+1 query pattern).
 */
class CourseAnalyticsService
{
    /**
     * @return array{total_enrollments: int, active_students: int, completed_students: int, completion_rate: float, average_course_progress: float, average_assessment_score: ?float, certificates_issued: int}
     */
    public function overview(SelfPacedCourse $course): array
    {
        $course->loadMissing(['modules.activities', 'modules.assessments']);

        $totalModules = $course->modules->count();
        $assessmentIds = $course->modules->flatMap(fn ($module) => $module->assessments->pluck('id'));

        $enrollments = Enrollment::query()
            ->where('self_paced_course_id', $course->id)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->get(['id', 'status']);

        $enrollmentIds = $enrollments->pluck('id');
        $totalEnrollments = $enrollments->count();
        $activeStudents = $enrollments->where('status', EnrollmentStatus::Active)->count();
        $completedStudents = $enrollments->where('status', EnrollmentStatus::Completed)->count();

        // One grouped query for every enrollment's completed-module count,
        // regardless of how many students the course has.
        $completedModuleCounts = $totalModules > 0
            ? ModuleProgress::query()
                ->whereIn('enrollment_id', $enrollmentIds)
                ->whereNotNull('completed_at')
                ->selectRaw('enrollment_id, count(*) as completed_count')
                ->groupBy('enrollment_id')
                ->pluck('completed_count', 'enrollment_id')
            : collect();

        $averageCourseProgress = $totalEnrollments > 0 && $totalModules > 0
            ? round($enrollmentIds->sum(fn ($id) => ($completedModuleCounts[$id] ?? 0) / $totalModules * 100) / $totalEnrollments, 2)
            : 0.0;

        $averageAssessmentScore = SelfPacedAssessmentAttempt::query()
            ->whereIn('self_paced_assessment_id', $assessmentIds)
            ->whereNotNull('percentage')
            ->avg('percentage');

        $certificatesIssued = CourseCertificate::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->count();

        return [
            'total_enrollments' => $totalEnrollments,
            'active_students' => $activeStudents,
            'completed_students' => $completedStudents,
            'completion_rate' => $totalEnrollments > 0 ? round($completedStudents / $totalEnrollments * 100, 2) : 0.0,
            'average_course_progress' => $averageCourseProgress,
            'average_assessment_score' => $averageAssessmentScore !== null ? round((float) $averageAssessmentScore, 2) : null,
            'certificates_issued' => $certificatesIssued,
        ];
    }
}
