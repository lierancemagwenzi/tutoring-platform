<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tutor's banking details, used by the admin to execute real-world
 * payouts (see PayoutService) — the platform has no payment-gateway payout
 * integration; this is the record of where to send the money, not a
 * payment mechanism itself. account_number is encrypted at rest, but
 * (unlike TutorConnectedAccount's OAuth tokens) both the tutor's own view
 * and the admin's view deliberately expose it in full, since that's the
 * entire point of collecting it — see TutorBankAccountResource /
 * BankAccountResource.
 */
class TutorBankAccount extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tutor_profile_id',
        'bank_name',
        'account_holder_name',
        'account_number',
        'branch_code',
        'account_type',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
        ];
    }

    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }
}
