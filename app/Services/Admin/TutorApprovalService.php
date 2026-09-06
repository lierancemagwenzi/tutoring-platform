<?php

namespace App\Services\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Resources\TutorApplicationResource;
use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reviews tutor applications — reuses the existing users.status field
 * (Pending/Approved/Rejected) that TutorRegistrationController and
 * SubmitTutorApplicationController already write to, adding the missing
 * admin-facing read/write side. Tutor account approval is tracked
 * independently from Tutor Subject approval (see TutorSubjectApprovalService)
 * — approving a tutor does not approve any of their requested subjects.
 */
class TutorApprovalService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function pending(int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->where('role', UserRole::Tutor)
            ->where('status', UserStatus::Pending)
            ->whereHas('tutorProfile')
            ->with(['tutorProfile' => fn ($q) => $q->withCount(['tutorSubjects', 'services', 'selfPacedCourses'])->with('bankAccount')])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(User $tutor): array
    {
        $profile = $tutor->tutorProfile;
        $profile->loadMissing([
            'tutorSubjects.subject',
            'tutorSubjects.grades',
            'services',
            'selfPacedCourses',
            'bankAccount',
            'qualifications',
            'documents',
        ]);

        return [
            'user' => [
                'id' => $tutor->id,
                'name' => trim("{$tutor->first_name} {$tutor->last_name}"),
                'email' => $tutor->email,
                'phone' => $tutor->phone,
                'email_verified' => $tutor->hasVerifiedEmail(),
                'status' => $tutor->status->value,
                'registered_at' => $tutor->created_at->toIso8601String(),
            ],
            // The full application — bio, languages, teaching style,
            // government ID, qualifications, supporting documents — the same
            // shape the tutor itself sees while filling it out.
            'profile' => (new TutorApplicationResource($profile))->resolve() + [
                'admin_note' => $profile->admin_note,
            ],
            'requested_subjects' => $profile->tutorSubjects->map(fn ($ts) => [
                'id' => $ts->id,
                'subject' => $ts->subject->name,
                'status' => $ts->status->value,
                'grades' => $ts->grades->pluck('name'),
            ])->values(),
            'services_count' => $profile->services->count(),
            'self_paced_courses_count' => $profile->selfPacedCourses->count(),
            // Full, unmasked — the admin needs it to actually pay this
            // tutor once approved. Approval itself is blocked without it —
            // see ApproveTutorRequest / BankingEligibilityService.
            'bank_account' => $profile->bankAccount ? [
                'bank_name' => $profile->bankAccount->bank_name,
                'account_holder_name' => $profile->bankAccount->account_holder_name,
                'account_number' => $profile->bankAccount->account_number,
                'branch_code' => $profile->bankAccount->branch_code,
                'account_type' => $profile->bankAccount->account_type,
            ] : null,
        ];
    }

    public function approve(User $tutor, User $actor): User
    {
        $tutor->update(['status' => UserStatus::Approved]);

        $this->logger->log($actor, 'tutor.approved', $tutor, "Approved tutor account for {$tutor->email}.");
        $tutor->notify(new UserNotification(
            type: 'tutor.approved',
            title: 'Application approved',
            body: 'Your tutor application has been approved. You can now start accepting bookings.',
            url: '/tutor',
        ));

        return $tutor;
    }

    public function reject(User $tutor, User $actor, string $reason): User
    {
        $tutor->update(['status' => UserStatus::Rejected]);
        $tutor->tutorProfile?->update(['admin_note' => $reason]);

        $this->logger->log($actor, 'tutor.rejected', $tutor, "Rejected tutor account for {$tutor->email}.", ['reason' => $reason]);
        $tutor->notify(new UserNotification(
            type: 'tutor.rejected',
            title: 'Application rejected',
            body: "Your tutor application was rejected: {$reason}",
            url: '/tutor',
        ));

        return $tutor;
    }

    public function requestChanges(User $tutor, User $actor, string $note): User
    {
        $tutor->update(['status' => UserStatus::Pending]);
        $tutor->tutorProfile?->update(['admin_note' => $note]);

        $this->logger->log($actor, 'tutor.changes_requested', $tutor, "Requested changes from tutor {$tutor->email}.", ['note' => $note]);
        $tutor->notify(new UserNotification(
            type: 'tutor.changes_requested',
            title: 'Changes requested',
            body: "An admin requested changes to your application: {$note}",
            url: '/tutor',
        ));

        return $tutor;
    }
}
