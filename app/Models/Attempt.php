<?php

namespace App\Models;

use App\Enums\AttemptProvider;
use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's attempt at an interactive, provider-scored Lesson Block (H5P,
 * SurveyJS, and future providers), scoped to the Session Lesson Block that
 * delivered it — never to the Lesson Block itself, which stays reusable
 * across unlimited sessions. One row per attempt (mirroring Submission and
 * the pre-existing QuizAttempt), so attempt history is natural.
 *
 * `provider` plus the two JSON payload columns are what keep this generic:
 * see App\Services\Attempts\AttemptResultHandler.
 */
class Attempt extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'session_lesson_block_id',
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
            'provider' => AttemptProvider::class,
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
     * The delivery instance this attempt was made against.
     */
    public function sessionLessonBlock(): BelongsTo
    {
        return $this->belongsTo(SessionLessonBlock::class);
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
