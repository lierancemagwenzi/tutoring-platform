<?php

namespace App\Services\Admin;

use App\Models\TutorProfile;

/**
 * Whether a tutor has banking details on file — the single source of
 * truth reused for tutor-side creation gates (StoreServiceRequest,
 * StoreSelfPacedCourseRequest) and the admin's payout gate
 * (MarkFinancialTransactionPaidRequest). Without this, the platform has
 * nowhere to send a tutor's earnings, so none of those actions are
 * allowed to proceed. Deliberately NOT required for account approval
 * (ApproveTutorRequest) — banking details aren't captured anywhere in
 * registration or the tutor application, only later via tutor/settings,
 * so requiring them upfront would leave every tutor unapprovable.
 * Approval just needs to happen before the tutor's first payout, not
 * before their account even exists.
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
