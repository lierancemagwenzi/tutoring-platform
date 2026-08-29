<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Assumes the caller has eager-loaded sessionLessonBlock.lessonBlock (for
     * max_score) and student/studentAttachments/feedbackAttachments as needed.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $maxScore = $this->sessionLessonBlock->lessonBlock->learningActivity()?->max_score;

        return [
            'id' => $this->id,
            'session_lesson_block_id' => $this->session_lesson_block_id,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'first_name' => $this->student->first_name,
                'last_name' => $this->student->last_name,
            ]),
            'attempt_number' => $this->attempt_number,
            'status' => $this->status->value,
            'submission_text' => $this->submission_text,
            'submitted_at' => $this->submitted_at,
            'score' => $this->score,
            'max_score' => $maxScore,
            'percentage' => $this->score !== null && $maxScore > 0 ? round(($this->score / $maxScore) * 100, 2) : null,
            'passing_score' => $this->sessionLessonBlock->passing_score,
            'passed' => $this->passed,
            'feedback_text' => $this->feedback_text,
            'reviewed_at' => $this->reviewed_at,
            'published_at' => $this->published_at,
            'student_attachments' => SubmissionAttachmentResource::collection($this->whenLoaded('studentAttachments')),
            'feedback_attachments' => SubmissionAttachmentResource::collection($this->whenLoaded('feedbackAttachments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
