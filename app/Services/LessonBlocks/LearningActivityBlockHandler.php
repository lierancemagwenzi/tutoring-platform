<?php

namespace App\Services\LessonBlocks;

use App\Contracts\LessonBlockHandler;
use App\Enums\LearningActivityType;
use App\Enums\LessonBlockStatus;
use App\Enums\SubmissionType;
use App\Models\LearningActivity;
use App\Models\LessonBlock;
use Illuminate\Http\Request;

abstract class LearningActivityBlockHandler implements LessonBlockHandler
{
    /**
     * The learning activity type this block type creates.
     */
    abstract protected function activityType(): LearningActivityType;

    /**
     * Per-type overrides merged over the base creation defaults below (e.g.
     * Practice/Reading default to ungraded, External defaults to no
     * submission). Empty by default — most types are fine with the base
     * defaults and only need activityType() overridden.
     *
     * @return array<string, mixed>
     */
    protected function defaultAttributes(): array
    {
        return [];
    }

    /**
     * The learning activity itself is managed through its own dedicated
     * endpoints, not through this block's own fields.
     *
     * @return array<string, mixed>
     */
    public function rules(bool $isUpdate): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContent(Request $request, ?LessonBlock $existing): array
    {
        if ($existing) {
            return $existing->content;
        }

        $activity = LearningActivity::create(array_merge([
            'lesson_id' => $request->route('lesson')->id,
            'type' => $this->activityType(),
            'title' => $request->input('title') ?: ucfirst($this->activityType()->value).' Untitled',
            'status' => LessonBlockStatus::Draft,
            'submission_type' => SubmissionType::Text,
        ], $this->defaultAttributes()));

        return ['learning_activity_id' => $activity->id];
    }

    public function afterDelete(LessonBlock $block): void {}

    /**
     * Clone the learning activity and its attachments into a new activity on
     * the same lesson, so editing the copy never affects the original.
     *
     * @return array<string, mixed>
     */
    public function duplicateContent(LessonBlock $original, LessonBlock $copy): array
    {
        $originalActivity = $original->learningActivity();

        if (! $originalActivity) {
            return [];
        }

        $newActivity = LearningActivity::create([
            'lesson_id' => $copy->lesson_id,
            'type' => $originalActivity->type,
            'title' => $originalActivity->title,
            'description' => $originalActivity->description,
            'instructions' => $originalActivity->instructions,
            'status' => LessonBlockStatus::Draft,
            'submission_type' => $originalActivity->submission_type,
            'max_score' => $originalActivity->max_score,
            'settings' => $originalActivity->settings,
        ]);

        foreach ($originalActivity->attachments as $attachment) {
            $newActivity->attachments()->create([
                'media_type' => $attachment->media_type,
                'title' => $attachment->title,
                'description' => $attachment->description,
                'file_path' => $attachment->file_path,
                'original_name' => $attachment->original_name,
                'size' => $attachment->size,
                'thumbnail_path' => $attachment->thumbnail_path,
                'position' => $attachment->position,
                'status' => $attachment->status,
            ]);
        }

        return ['learning_activity_id' => $newActivity->id];
    }
}
