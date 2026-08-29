<?php

namespace App\Http\Resources\Student;

use App\Enums\LessonBlockType;
use App\Http\Resources\H5pContentResource;
use App\Http\Resources\LearningActivityResource;
use App\Http\Resources\MediaItemResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonBlockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Mirrors App\Http\Resources\LessonBlockResource, but nests the
     * student-safe Student\QuizResource — the tutor-facing QuizResource
     * includes each question's correct_answer, which must never reach a
     * student who hasn't attempted the quiz yet.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'block_type' => $this->block_type->value,
            'position' => $this->position,
            'title' => $this->title,
            'content' => $this->content,
            'settings' => $this->settings,
            'status' => $this->status->value,
            'media_items' => $this->when(
                $this->block_type === LessonBlockType::Media,
                fn () => MediaItemResource::collection($this->whenLoaded('mediaItems')),
            ),
            'quiz' => $this->when(
                $this->block_type === LessonBlockType::Quiz,
                fn () => ($quiz = $this->quiz()) ? new QuizResource($quiz->load('questions')) : null,
            ),
            'h5p_content' => $this->when(
                $this->block_type === LessonBlockType::H5p,
                fn () => ($content = $this->h5pContent()) ? new H5pContentResource($content) : null,
            ),
            'learning_activity' => $this->when(
                in_array($this->block_type, [
                    LessonBlockType::Assignment,
                    LessonBlockType::Homework,
                    LessonBlockType::Practice,
                    LessonBlockType::Assessment,
                    LessonBlockType::Project,
                    LessonBlockType::Lab,
                    LessonBlockType::Reflection,
                    LessonBlockType::Reading,
                    LessonBlockType::External,
                ], true),
                fn () => ($activity = $this->learningActivity()) ? new LearningActivityResource($activity->load('attachments')) : null,
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
