<?php

namespace App\Services\Admin;

use App\Enums\TutorSubjectStatus;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The tutor-facing admin management view — read-only visibility into a
 * tutor's approval state, subjects, offerings, and activity. Distinct from
 * TutorApprovalService (the pending-application review queue) and
 * TutorSubjectApprovalService (individual subject decisions) — this is the
 * "look up any tutor, at any time" directory.
 */
class AdminTutorManagementService
{
    /**
     * @param  array{search?: string}  $filters
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = TutorProfile::query()
            ->with('user')
            ->withCount(['services', 'selfPacedCourses', 'bookings']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('display_name', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")));
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(TutorProfile $tutor): array
    {
        $tutor->loadMissing(['user', 'tutorSubjects.subject', 'connectedAccounts', 'bankAccount']);
        $tutor->loadCount(['services', 'selfPacedCourses', 'bookings', 'teachingSessions']);

        $subjectsByStatus = $tutor->tutorSubjects->groupBy(fn ($ts) => $ts->status->value);

        return [
            'id' => $tutor->id,
            'display_name' => $tutor->display_name,
            'bio' => $tutor->bio,
            'years_experience' => $tutor->years_experience,
            'user' => [
                'id' => $tutor->user->id,
                'email' => $tutor->user->email,
                'email_verified' => $tutor->user->hasVerifiedEmail(),
                'approval_status' => $tutor->user->status->value,
                'disabled' => $tutor->user->disabled_at !== null,
            ],
            'approved_subjects' => $this->subjectsSummary($subjectsByStatus->get(TutorSubjectStatus::Approved->value, collect())),
            'pending_subjects' => $this->subjectsSummary($subjectsByStatus->get(TutorSubjectStatus::Pending->value, collect())),
            'rejected_subjects' => $this->subjectsSummary($subjectsByStatus->get(TutorSubjectStatus::Rejected->value, collect())),
            'suspended_subjects' => $this->subjectsSummary($subjectsByStatus->get(TutorSubjectStatus::Suspended->value, collect())),
            'services_count' => $tutor->services_count,
            'self_paced_courses_count' => $tutor->self_paced_courses_count,
            'bookings_count' => $tutor->bookings_count,
            'teaching_sessions_count' => $tutor->teaching_sessions_count,
            'connected_meeting_providers' => $tutor->connectedAccounts->map(fn ($account) => [
                'provider' => $account->provider->value,
                'email' => $account->email,
                'connected_at' => $account->connected_at?->toIso8601String(),
                'expires_at' => $account->expires_at?->toIso8601String(),
            ])->values(),
            // Full, unmasked — the admin needs the real account details to
            // actually pay this tutor. See TutorBankAccount's docblock.
            'bank_account' => $tutor->bankAccount ? [
                'bank_name' => $tutor->bankAccount->bank_name,
                'account_holder_name' => $tutor->bankAccount->account_holder_name,
                'account_number' => $tutor->bankAccount->account_number,
                'branch_code' => $tutor->bankAccount->branch_code,
                'account_type' => $tutor->bankAccount->account_type,
            ] : null,
        ];
    }

    /**
     * @param  Collection<int, TutorSubject>  $tutorSubjects
     * @return list<array{id: int, subject: string}>
     */
    private function subjectsSummary(Collection $tutorSubjects): array
    {
        return $tutorSubjects->map(fn ($ts) => ['id' => $ts->id, 'subject' => $ts->subject->name])->values()->all();
    }
}
