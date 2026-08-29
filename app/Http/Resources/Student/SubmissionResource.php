<?php

namespace App\Http\Resources\Student;

use App\Http\Resources\SubmissionAttachmentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Score, pass/fail, and feedback are withheld until the tutor
     * publishes the result — the student sees only that it's under
     * review in the meantime, never the grade itself.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isPublished = $this->published_at !== null;
        $maxScore = $this->sessionLessonBlock->lessonBlock->learningActivity()?->max_score;

        return [
            'id' => $this->id,
            'session_lesson_block_id' => $this->session_lesson_block_id,
            'attempt_number' => $this->attempt_number,
            'status' => $this->status->value,
            'submission_text' => $this->submission_text,
            'submitted_at' => $this->submitted_at,
            'score' => $isPublished ? $this->score : null,
            'max_score' => $isPublished ? $maxScore : null,
            'percentage' => $isPublished && $this->score !== null && $maxScore > 0
                ? round(($this->score / $maxScore) * 100, 2) : null,
            'passed' => $isPublished ? $this->passed : null,
            'feedback_text' => $isPublished ? $this->feedback_text : null,
            'feedback_attachments' => $isPublished
                ? SubmissionAttachmentResource::collection($this->whenLoaded('feedbackAttachments'))
                : [],
            'is_published' => $isPublished,
            'student_attachments' => SubmissionAttachmentResource::collection($this->whenLoaded('studentAttachments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
