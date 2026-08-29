<?php

namespace App\Services\Admin;

use App\Models\TutorProfile;

/**
 * Whether a tutor has banking details on file — the single source of
 * truth reused both for tutor-side creation gates (StoreServiceRequest,
 * StoreSelfPacedCourseRequest) and the admin's tutor-approval gate
 * (ApproveTutorRequest). Without this, the platform has nowhere to send a
 * tutor's earnings, so neither action is allowed to proceed.
 */
class BankingEligibilityService
{
    /**
     * @return list<string>
     */
    public function errorsFor(?TutorProfile $tutor): array
    {
        if (! $tutor || ! $tutor->bankAccount || ! $tutor->bankAccount->account_number) {
            return ['You must add your banking details before this action is available.'];
        }

        return [];
    }
}
