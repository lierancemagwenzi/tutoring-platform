<?php

namespace App\Http\Resources;

use App\Enums\LessonBlockType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonBlockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
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
            // The Subject/Grade/Curriculum this block's ancestor Course is
            // classified under — only H5P content matching all three is
            // eligible to attach here (see H5pBlockHandler::rules()); the
            // tutor-facing picker (H5pManager.vue) uses this to scope its
            // "choose from your library" list instead of showing everything.
            'course_classification' => $this->when(
                $this->block_type === LessonBlockType::H5p,
                function () {
                    $course = $this->lesson->chapter->course->loadMissing(['grade', 'subject', 'curriculum']);

                    return [
                        'grade' => ['id' => $course->grade->id, 'name' => $course->grade->name],
                        'subject' => ['id' => $course->subject->id, 'name' => $course->subject->name],
                        'curriculum' => ['id' => $course->curriculum->id, 'name' => $course->curriculum->name],
                    ];
                },
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
