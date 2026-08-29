<?php

namespace App\Services\Submissions;

use App\Enums\MediaType;
use App\Enums\SubmissionAttachmentCategory;
use App\Enums\SubmissionStatus;
use App\Models\SessionLessonBlock;
use App\Models\Submission;
use App\Models\SubmissionAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SubmissionService
{
    /**
     * Resume the student's in-progress draft for this delivery instance, or
     * start a new attempt if none is open. Attempt numbering only counts
     * attempts that were actually submitted — an abandoned draft never
     * consumes a slot.
     */
    public function startOrResumeDraft(SessionLessonBlock $sessionLessonBlock, User $student): Submission
    {
        $draft = $sessionLessonBlock->submissions()
            ->where('student_id', $student->id)
            ->where('status', SubmissionStatus::Draft)
            ->first();

        if ($draft) {
            return $draft;
        }

        return $sessionLessonBlock->submissions()->create([
            'student_id' => $student->id,
            'attempt_number' => $sessionLessonBlock->attemptsUsedBy($student->id) + 1,
            'status' => SubmissionStatus::Draft,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(Submission $submission, array $data): Submission
    {
        $submission->update([
            'submission_text' => [
                'html' => $data['submission_text_html'] ?? null,
                'json' => $data['submission_text_json'] ?? null,
            ],
        ]);

        return $submission;
    }

    /**
     * Transition a draft into submitted, locking it against further edits.
     */
    public function submit(Submission $submission): Submission
    {
        $submission->update([
            'status' => SubmissionStatus::Submitted,
            'submitted_at' => now(),
        ]);

        return $submission;
    }

    public function markUnderReview(Submission $submission): Submission
    {
        $submission->update(['status' => SubmissionStatus::UnderReview]);

        return $submission;
    }

    public function returnForRevision(Submission $submission): Submission
    {
        $submission->update(['status' => SubmissionStatus::Returned]);

        return $submission;
    }

    /**
     * Grade a submission. Pass/fail is derived from the Passing Score
     * already configured on the delivery instance (Session Lesson Block) —
     * never a separately stored rule.
     *
     * @param  array<string, mixed>|null  $feedback
     */
    public function grade(Submission $submission, float $score, ?array $feedback, User $reviewer): Submission
    {
        $passingScore = $submission->sessionLessonBlock->passing_score;

        $submission->update([
            'status' => SubmissionStatus::Graded,
            'score' => $score,
            'passed' => $passingScore !== null ? $score >= (float) $passingScore : null,
            'feedback_text' => $feedback ? ['html' => $feedback['html'] ?? null, 'json' => $feedback['json'] ?? null] : $submission->feedback_text,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
        ]);

        return $submission;
    }

    /**
     * Reveal a graded submission's score/feedback to the student.
     */
    public function publish(Submission $submission): Submission
    {
        $submission->update(['published_at' => now()]);

        return $submission;
    }

    public function addAttachment(
        Submission $submission,
        SubmissionAttachmentCategory $category,
        MediaType $mediaType,
        UploadedFile $file,
        ?string $title,
    ): SubmissionAttachment {
        return $submission->attachments()->create([
            'category' => $category,
            'media_type' => $mediaType,
            'title' => $title,
            'file_path' => $file->store('submission-attachments', 'public'),
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'position' => $submission->attachments()->where('category', $category)->count(),
        ]);
    }

    public function removeAttachment(SubmissionAttachment $attachment): void
    {
        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();
    }
}
