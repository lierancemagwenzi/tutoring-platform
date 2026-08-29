<?php

namespace App\Models;

use App\Enums\AttemptsMode;
use App\Enums\AttemptStatus;
use App\Enums\CompletionCondition;
use App\Enums\SessionLessonBlockAvailabilityMode;
use App\Enums\SessionLessonBlockCompletionMode;
use App\Enums\SessionLessonBlockVisibility;
use App\Enums\SubmissionStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The delivery layer: how a Lesson Block behaves within a specific Session,
 * as distinct from the Lesson Block itself which stays a reusable,
 * delivery-agnostic educational resource. Every setting here (availability,
 * completion, attempts, passing score, visibility) is scoped to this one
 * assignment and never touches the underlying LessonBlock/LearningActivity.
 */
class SessionLessonBlock extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'session_lesson_id',
        'lesson_block_id',
        'availability_mode',
        'available_from',
        'available_until',
        'opens_at',
        'closes_at',
        'is_manually_released',
        'completion_mode',
        'completion_rule',
        'attempts_mode',
        'max_attempts',
        'passing_score',
        'visibility',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'availability_mode' => SessionLessonBlockAvailabilityMode::class,
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'is_manually_released' => 'boolean',
            'completion_mode' => SessionLessonBlockCompletionMode::class,
            'completion_rule' => CompletionCondition::class,
            'attempts_mode' => AttemptsMode::class,
            'max_attempts' => 'integer',
            'passing_score' => 'decimal:2',
            'visibility' => SessionLessonBlockVisibility::class,
        ];
    }

    /**
     * The session/lesson pairing this block assignment belongs to.
     */
    public function sessionLesson(): BelongsTo
    {
        return $this->belongsTo(SessionLesson::class);
    }

    /**
     * The lesson block being delivered — referenced, never duplicated.
     */
    public function lessonBlock(): BelongsTo
    {
        return $this->belongsTo(LessonBlock::class);
    }

    /**
     * The student submission attempts made against this delivery instance.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * The interactive provider attempts (H5P, SurveyJS, ...) made against
     * this delivery instance — the Attempt Engine's own records, entirely
     * separate from submissions() above.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    /**
     * Whether a student can currently access this block, per its
     * availability strategy. The single source of truth for both the tutor
     * management screen (status display) and the student endpoint
     * (filtering).
     */
    public function isAvailable(): bool
    {
        $now = Carbon::now();

        return match ($this->availability_mode) {
            SessionLessonBlockAvailabilityMode::AlwaysAvailable => true,
            SessionLessonBlockAvailabilityMode::Immediate => $now->greaterThanOrEqualTo($this->sessionStartsAt()),
            SessionLessonBlockAvailabilityMode::ManualRelease => $this->is_manually_released,
            SessionLessonBlockAvailabilityMode::ScheduledRelease => $this->available_from
                && $now->greaterThanOrEqualTo($this->available_from)
                && (! $this->available_until || $now->lessThanOrEqualTo($this->available_until)),
            SessionLessonBlockAvailabilityMode::AssessmentWindow => $this->opens_at && $this->closes_at
                && $now->between($this->opens_at, $this->closes_at),
        };
    }

    /**
     * The moment the underlying teaching session starts, used by the
     * "immediate" availability mode.
     */
    protected function sessionStartsAt(): Carbon
    {
        $session = $this->sessionLesson->teachingSession;

        return Carbon::parse($session->date->format('Y-m-d').' '.$session->start_time);
    }

    /**
     * How many attempts this student has already used against this delivery
     * instance — a draft in progress doesn't consume an attempt, only a
     * submission that was actually submitted does.
     */
    public function attemptsUsedBy(int $studentId): int
    {
        return $this->submissions()
            ->where('student_id', $studentId)
            ->where('status', '!=', SubmissionStatus::Draft)
            ->count();
    }

    /**
     * Whether this student may still start a new attempt, per this
     * delivery instance's own attempt configuration — never a
     * separately-tracked limit.
     */
    public function hasAttemptsRemainingFor(int $studentId): bool
    {
        if ($this->attempts_mode !== AttemptsMode::Limited) {
            return true;
        }

        return $this->attemptsUsedBy($studentId) < ($this->max_attempts ?? 0);
    }

    /**
     * How many Attempt Engine attempts (H5P/SurveyJS/...) this student has
     * already used against this delivery instance — a still-open attempt
     * (started/in_progress) is resumed rather than consuming a slot, only a
     * finished one (completed, abandoned, timed out, ...) does. The
     * Submission Engine's own attemptsUsedBy() above counts a different
     * relation (submissions) and is untouched by this.
     */
    public function interactiveAttemptsUsedBy(int $studentId): int
    {
        return $this->attempts()
            ->where('student_id', $studentId)
            ->whereNotIn('status', [AttemptStatus::Started->value, AttemptStatus::InProgress->value])
            ->count();
    }

    /**
     * Whether this student may still start (or resume) an Attempt Engine
     * attempt, per this delivery instance's own attempt configuration.
     */
    public function hasAttemptCapacityFor(int $studentId): bool
    {
        if ($this->attempts_mode !== AttemptsMode::Limited) {
            return true;
        }

        return $this->interactiveAttemptsUsedBy($studentId) < ($this->max_attempts ?? 0);
    }
}
