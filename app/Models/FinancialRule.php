<?php

namespace App\Models;

use App\Enums\FinancialRuleScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A commission configuration at one of four scopes (global/tutor/service/
 * course). The resolver (FinancialRuleResolverService) picks the most
 * specific active rule applicable to a given purchase; this model is pure
 * configuration — it is never mutated once a FinancialTransaction has
 * referenced it, since transactions snapshot the computed amounts rather
 * than recomputing from the live rule.
 *
 * Effective-dated: changing a rate creates a NEW row with its own
 * effective_from rather than mutating the existing one in place (see
 * FinancialRuleManagementService), so multiple rows can exist for the same
 * scope/target over time — the resolver picks whichever one's
 * effective_from has already passed, most recent first. This is what makes
 * AC-04/AC-05 hold: scheduling a future rate change never touches a
 * booking made today.
 */
class FinancialRule extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'scope',
        'tutor_profile_id',
        'service_id',
        'self_paced_course_id',
        'percentage',
        'fixed_fee',
        'provider_fee_percentage',
        'provider_fee_fixed',
        'currency',
        'is_active',
        'created_by',
        'effective_from',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => FinancialRuleScope::class,
            'percentage' => 'decimal:2',
            'fixed_fee' => 'decimal:2',
            'provider_fee_percentage' => 'decimal:2',
            'provider_fee_fixed' => 'decimal:2',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A row with no effective_from would never resolve (NULL <= now()
        // is NULL, not true, in SQL) — default to "apply immediately" at
        // the model level so this holds even for a raw create() that
        // bypasses FinancialRuleManagementService (tests, tinker, etc.),
        // not just the service's own explicit default.
        static::creating(function (self $rule) {
            $rule->effective_from ??= now();
        });
    }

    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function selfPacedCourse(): BelongsTo
    {
        return $this->belongsTo(SelfPacedCourse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
