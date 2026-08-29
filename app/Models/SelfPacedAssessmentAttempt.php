<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use App\Enums\SelfPacedAssessmentProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's attempt at a self-paced Assessment (SurveyJS or H5P), scoped
 * directly to the Assessment itself since — unlike Tutor-Led Learning's
 * Attempt/SessionLessonBlock split — a self-paced Assessment has no
 * per-delivery-instance wrapper; the Assessment is already the reusable
 * definition and this is the one-row-per-attempt ledger against it.
 * Mirrors App\Models\Attempt's shape deliberately, scoped to this domain —
 * see App\Services\LearnerProgress\SelfPacedAssessmentAttemptService.
 */
class SelfPacedAssessmentAttempt extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'self_paced_assessment_id',
        'student_id',
        'provider',
        'attempt_number',
        'status',
        'started_at',
        'completed_at',
        'time_taken_seconds',
        'raw_score',
        'max_score',
        'percentage',
        'passed',
        'raw_provider_response',
        'provider_metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => SelfPacedAssessmentProvider::class,
            'attempt_number' => 'integer',
            'status' => AttemptStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'time_taken_seconds' => 'integer',
            'raw_score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'percentage' => 'decimal:2',
            'passed' => 'boolean',
            'raw_provider_response' => 'array',
            'provider_metadata' => 'array',
        ];
    }

    /**
     * The assessment this attempt was made against.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(SelfPacedAssessment::class, 'self_paced_assessment_id');
    }

    /**
     * The student who made this attempt.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Whether this attempt is still open for the provider to report progress/results on.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, [AttemptStatus::Started, AttemptStatus::InProgress], true);
    }
}
