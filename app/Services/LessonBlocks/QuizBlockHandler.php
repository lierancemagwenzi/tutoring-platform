<?php

namespace App\Services\LessonBlocks;

use App\Contracts\LessonBlockHandler;
use App\Enums\LessonBlockStatus;
use App\Models\LessonBlock;
use App\Models\Quiz;
use Illuminate\Http\Request;

class QuizBlockHandler implements LessonBlockHandler
{
    /**
     * The quiz itself is managed through its own dedicated endpoints, not
     * through this block's own fields, so there is nothing extra to validate.
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

        $quiz = Quiz::create([
            'lesson_id' => $request->route('lesson')->id,
            'title' => $request->input('title') ?: 'Untitled Quiz',
            'status' => LessonBlockStatus::Draft,
            'settings' => [],
        ]);

        return ['quiz_id' => $quiz->id];
    }

    public function afterDelete(LessonBlock $block): void {}

    /**
     * Clone the quiz and its questions into a brand new quiz on the same
     * lesson, so editing the copy never affects the original.
     *
     * @return array<string, mixed>
     */
    public function duplicateContent(LessonBlock $original, LessonBlock $copy): array
    {
        $originalQuiz = $original->quiz();

        if (! $originalQuiz) {
            return [];
        }

        $newQuiz = Quiz::create([
            'lesson_id' => $copy->lesson_id,
            'title' => $originalQuiz->title,
            'description' => $originalQuiz->description,
            'status' => LessonBlockStatus::Draft,
            'settings' => $originalQuiz->settings,
        ]);

        foreach ($originalQuiz->questions as $question) {
            $newQuiz->questions()->create([
                'position' => $question->position,
                'type' => $question->type,
                'definition' => $question->definition,
                'points' => $question->points,
            ]);
        }

        return ['quiz_id' => $newQuiz->id];
    }
}
