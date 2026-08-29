<?php

namespace App\Http\Requests\Student;

use App\Enums\ChapterStatus;
use App\Enums\LessonBlockStatus;
use App\Enums\LessonStatus;
use App\Services\Lms\StudentCourseAccessService;
use Illuminate\Foundation\Http\FormRequest;

class StartQuizAttemptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $quiz = $this->route('quiz');
        $lesson = $quiz->lesson;
        $chapter = $lesson->chapter;

        return $quiz->status === LessonBlockStatus::Published
            && $lesson->status === LessonStatus::Published
            && $chapter->status === ChapterStatus::Published
            && app(StudentCourseAccessService::class)->canAccess($this->user(), $chapter->course);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
