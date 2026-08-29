<?php

namespace App\Services\Admin;

use App\Enums\TutorSubjectStatus;
use App\Models\TutorSubject;
use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reviews individual tutor-subject requests. This is a decision entirely
 * separate from tutor account approval (see TutorApprovalService) — a
 * tutor being approved does not approve any of their requested subjects,
 * and a subject being approved for one tutor says nothing about any other
 * tutor's request for the same subject.
 */
class TutorSubjectApprovalService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function pending(int $perPage = 15): LengthAwarePaginator
    {
        return TutorSubject::query()
            ->where('status', TutorSubjectStatus::Pending)
            ->with(['tutorProfile.user', 'subject'])
            ->latest()
            ->paginate($perPage);
    }

    public function approve(TutorSubject $tutorSubject, User $actor): TutorSubject
    {
        $tutorSubject->update([
            'status' => TutorSubjectStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $actor->id,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        $this->logger->log(
            $actor,
            'tutor_subject.approved',
            $tutorSubject,
            "Approved subject \"{$tutorSubject->subject->name}\" for tutor #{$tutorSubject->tutor_profile_id}.",
        );

        $tutorSubject->loadMissing('tutorProfile.user');
        $tutorSubject->tutorProfile->user->notify(new UserNotification(
            type: 'tutor_subject.approved',
            title: 'Subject approved',
            body: "Your request to teach \"{$tutorSubject->subject->name}\" was approved.",
            url: '/tutor',
        ));

        return $tutorSubject;
    }

    public function reject(TutorSubject $tutorSubject, User $actor, string $reason): TutorSubject
    {
        $tutorSubject->update([
            'status' => TutorSubjectStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->logger->log(
            $actor,
            'tutor_subject.rejected',
            $tutorSubject,
            "Rejected subject \"{$tutorSubject->subject->name}\" for tutor #{$tutorSubject->tutor_profile_id}.",
            ['reason' => $reason],
        );

        $tutorSubject->loadMissing('tutorProfile.user');
        $tutorSubject->tutorProfile->user->notify(new UserNotification(
            type: 'tutor_subject.rejected',
            title: 'Subject rejected',
            body: "Your request to teach \"{$tutorSubject->subject->name}\" was rejected: {$reason}",
            url: '/tutor',
        ));

        return $tutorSubject;
    }

    /**
     * Blocks new offerings under this subject going forward — does not
     * retroactively unpublish anything already live, since the eligibility
     * gate only runs at publish-time (see MarketplaceEligibilityService).
     */
    public function suspend(TutorSubject $tutorSubject, User $actor, string $reason): TutorSubject
    {
        $tutorSubject->update([
            'status' => TutorSubjectStatus::Suspended,
            'rejection_reason' => $reason,
        ]);

        $this->logger->log(
            $actor,
            'tutor_subject.suspended',
            $tutorSubject,
            "Suspended subject \"{$tutorSubject->subject->name}\" for tutor #{$tutorSubject->tutor_profile_id}.",
            ['reason' => $reason],
        );

        $tutorSubject->loadMissing('tutorProfile.user');
        $tutorSubject->tutorProfile->user->notify(new UserNotification(
            type: 'tutor_subject.suspended',
            title: 'Subject suspended',
            body: "Your ability to teach \"{$tutorSubject->subject->name}\" was suspended: {$reason}",
            url: '/tutor',
        ));

        return $tutorSubject;
    }
}
