<?php

namespace App\Models;

use App\Enums\SubmissionAttachmentCategory;
use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's attempt at a submission-based Lesson Block, scoped to the
 * Session Lesson Block that delivered it — never to the Lesson Block
 * itself, which stays reusable across unlimited sessions. One row per
 * attempt (mirroring QuizAttempt), so attempt history and per-attempt
 * grading are natural.
 */
class Submission extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'session_lesson_block_id',
        'student_id',
        'attempt_number',
        'status',
        'submission_text',
        'submitted_at',
        'score',
        'passed',
        'feedback_text',
        'reviewed_at',
        'reviewed_by',
        'published_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'status' => SubmissionStatus::class,
            'submission_text' => 'array',
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
            'passed' => 'boolean',
            'feedback_text' => 'array',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * The delivery instance this submission was made against.
     */
    public function sessionLessonBlock(): BelongsTo
    {
        return $this->belongsTo(SessionLessonBlock::class);
    }

    /**
     * The student who made this submission.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * The tutor who reviewed/graded this submission, if any.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * All attachments on this submission, both the student's files and the
     * tutor's feedback files. Prefer studentAttachments()/feedbackAttachments()
     * when only one side is needed.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(SubmissionAttachment::class)->orderBy('position');
    }

    /**
     * The files the student submitted.
     */
    public function studentAttachments(): HasMany
    {
        return $this->attachments()->where('category', SubmissionAttachmentCategory::Student);
    }

    /**
     * The files the tutor attached as feedback (e.g. an annotated PDF).
     */
    public function feedbackAttachments(): HasMany
    {
        return $this->attachments()->where('category', SubmissionAttachmentCategory::Feedback);
    }

    /**
     * Only a draft can still be edited by the student.
     */
    public function isEditable(): bool
    {
        return $this->status === SubmissionStatus::Draft;
    }
}
