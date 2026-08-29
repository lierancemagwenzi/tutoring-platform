<?php

namespace App\Services\Admin;

use App\Enums\SubjectStatus;
use App\Enums\TutorSubjectStatus;
use App\Enums\UserStatus;
use App\Models\Subject;
use App\Models\TutorProfile;

/**
 * Determines whether a tutor may publish a marketplace offering (a
 * Service or a SelfPacedCourse) — called from the tutor-side publish
 * FormRequests (PublishServiceRequest, PublishSelfPacedCourseRequest), not
 * exposed as its own admin endpoint. Two independent conditions must both
 * hold: the tutor's account is approved, and the tutor has an approved
 * TutorSubject relationship for the specific subject being published
 * under. Creating a Draft is never blocked — only publishing is.
 */
class MarketplaceEligibilityService
{
    /**
     * @return list<string>
     */
    public function tutorEligibilityErrors(TutorProfile $tutor): array
    {
        if ($tutor->user->status !== UserStatus::Approved) {
            return ['Your tutor account is not yet approved.'];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public function subjectEligibilityErrors(TutorProfile $tutor, ?int $subjectId): array
    {
        if ($subjectId === null) {
            return ['A subject must be selected before publishing.'];
        }

        $subject = Subject::find($subjectId);

        if (! $subject || $subject->status !== SubjectStatus::Active) {
            return ['This subject is not currently active on the platform.'];
        }

        $tutorSubject = $tutor->tutorSubjects()->where('subject_id', $subjectId)->first();

        return match (true) {
            $tutorSubject === null => ["You have not requested to teach \"{$subject->name}\" yet."],
            $tutorSubject->status === TutorSubjectStatus::Pending => ["Your request to teach \"{$subject->name}\" is still awaiting approval."],
            $tutorSubject->status === TutorSubjectStatus::Rejected => ["Your request to teach \"{$subject->name}\" was rejected."],
            $tutorSubject->status === TutorSubjectStatus::Suspended => ["Your permission to teach \"{$subject->name}\" has been suspended."],
            default => [],
        };
    }
}
