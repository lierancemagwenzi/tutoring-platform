<?php

namespace App\Services\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\SelfPacedCourseStatus;
use App\Enums\ServiceVisibility;
use App\Enums\SessionStatus;
use App\Enums\SubjectStatus;
use App\Enums\TutorSubjectStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\CourseCertificate;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SelfPacedCourse;
use App\Models\Service;
use App\Models\Subject;
use App\Models\TeachingSession;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Platform-wide statistics for the Admin Dashboard — every number here is a
 * grouped aggregate query, never a loop over full datasets.
 */
class AdminDashboardService
{
    public function __construct(private readonly IntegrationStatusService $integrations) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return Cache::remember('admin.dashboard.overview', now()->addMinutes(5), fn () => [
            'users' => $this->userStats(),
            'subjects' => $this->subjectStats(),
            'tutoring' => $this->tutoringStats(),
            'self_paced' => $this->selfPacedStats(),
            'payments' => $this->paymentStats(),
        ]);
    }

    /**
     * @return list<array{severity: string, message: string, link: string}>
     */
    public function actionRequired(): array
    {
        $alerts = [];

        $pendingTutors = User::where('role', UserRole::Tutor)->where('status', UserStatus::Pending)->count();
        if ($pendingTutors > 0) {
            $alerts[] = ['severity' => 'warning', 'message' => "{$pendingTutors} tutors awaiting approval", 'link' => '/admin/tutors/pending'];
        }

        $pendingSubjectRequests = TutorSubject::where('status', TutorSubjectStatus::Pending)->count();
        if ($pendingSubjectRequests > 0) {
            $alerts[] = ['severity' => 'warning', 'message' => "{$pendingSubjectRequests} tutor subject requests awaiting approval", 'link' => '/admin/tutor-subject-requests'];
        }

        if (Subject::active()->doesntExist()) {
            $alerts[] = ['severity' => 'warning', 'message' => 'No active subjects configured', 'link' => '/admin/subjects'];
        }

        foreach ($this->integrations->status() as $key => $integration) {
            if ($integration['status'] === 'needs_configuration') {
                $alerts[] = ['severity' => 'warning', 'message' => "{$integration['name']} is not configured", 'link' => '/admin/integrations'];
            }
        }

        return $alerts;
    }

    /**
     * @return array<string, mixed>
     */
    private function userStats(): array
    {
        return [
            'total_students' => User::where('role', UserRole::Student)->count(),
            'total_tutors' => User::where('role', UserRole::Tutor)->count(),
            'verified_students' => User::where('role', UserRole::Student)->whereNotNull('email_verified_at')->count(),
            'verified_tutors' => User::where('role', UserRole::Tutor)->whereNotNull('email_verified_at')->count(),
            'pending_tutor_accounts' => User::where('role', UserRole::Tutor)->where('status', UserStatus::Pending)->count(),
            'recently_registered' => User::latest()->limit(5)->get(['id', 'first_name', 'last_name', 'email', 'role', 'created_at'])
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => trim("{$user->first_name} {$user->last_name}"),
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'registered_at' => $user->created_at->toIso8601String(),
                ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subjectStats(): array
    {
        return [
            'total_subjects' => Subject::count(),
            'active_subjects' => Subject::where('status', SubjectStatus::Active)->count(),
            'inactive_subjects' => Subject::whereIn('status', [SubjectStatus::Inactive, SubjectStatus::Archived])->count(),
            'pending_tutor_subject_requests' => TutorSubject::where('status', TutorSubjectStatus::Pending)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tutoringStats(): array
    {
        return [
            'active_tutoring_services' => Service::where('visibility', ServiceVisibility::Published)->count(),
            'total_bookings' => Booking::count(),
            'upcoming_sessions' => TeachingSession::where('status', SessionStatus::Scheduled)->count(),
            'completed_sessions' => TeachingSession::where('status', SessionStatus::Completed)->count(),
            'cancelled_sessions' => TeachingSession::where('status', SessionStatus::Cancelled)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function selfPacedStats(): array
    {
        return [
            'published_courses' => SelfPacedCourse::where('status', SelfPacedCourseStatus::Published)->count(),
            'draft_courses' => SelfPacedCourse::where('status', SelfPacedCourseStatus::Draft)->count(),
            'total_enrollments' => Enrollment::count(),
            'active_learners' => Enrollment::where('status', EnrollmentStatus::Active)->distinct('student_id')->count('student_id'),
            'completed_courses' => Enrollment::where('status', EnrollmentStatus::Completed)->count(),
            'certificates_issued' => CourseCertificate::count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentStats(): array
    {
        $byStatus = Payment::query()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');

        return [
            'total_payments' => Payment::count(),
            'successful_payments' => (int) ($byStatus['successful'] ?? 0),
            'failed_payments' => (int) ($byStatus['failed'] ?? 0),
            'pending_payments' => (int) ($byStatus['pending'] ?? 0),
            'recent_transactions' => Payment::with('order.student')->latest()->limit(5)->get()
                ->map(fn ($payment) => [
                    'id' => $payment->id,
                    'reference' => $payment->payment_reference,
                    'student' => $payment->order?->student ? trim("{$payment->order->student->first_name} {$payment->order->student->last_name}") : null,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status->value,
                    'created_at' => $payment->created_at->toIso8601String(),
                ])->values(),
        ];
    }
}
