<?php

namespace App\Services\LessonBlocks;

use App\Contracts\LessonBlockHandler;
use App\Models\LessonBlock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class H5pBlockHandler implements LessonBlockHandler
{
    /**
     * The H5P content itself is authored/selected through the dedicated H5P
     * server (see App\Services\H5p\H5PService); Laravel only stores its
     * content id, which is only meaningful once the block already exists
     * (set via update). Every piece of content belongs to exactly one tutor
     * (see App\Models\H5pContentClassification) — a tutor may only attach
     * their own content, and only content classified under the exact same
     * Grade/Subject/Curriculum as this block's ancestor Course (mirroring
     * LessonBlockResource's course_classification, which the tutor-facing
     * picker uses to scope what it offers in the first place).
     *
     * @return array<string, mixed>
     */
    public function rules(bool $isUpdate): array
    {
        if (! $isUpdate) {
            return [];
        }

        $rule = Rule::exists('h5p_content_classifications', 'h5p_content_id')
            ->where('tutor_profile_id', auth()->user()?->tutorProfile?->id);

        $course = request()->route('lessonBlock')?->lesson?->chapter?->course;
        if ($course) {
            $rule->where('grade_id', $course->grade_id)
                ->where('subject_id', $course->subject_id)
                ->where('curriculum_id', $course->curriculum_id);
        }

        return [
            'h5p_content_id' => ['nullable', 'string', $rule],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContent(Request $request, ?LessonBlock $existing): array
    {
        if (! $existing) {
            return [];
        }

        return $request->filled('h5p_content_id')
            ? ['h5p_content_id' => (string) $request->input('h5p_content_id')]
            : $existing->content;
    }

    public function afterDelete(LessonBlock $block): void {}

    /**
     * H5P content is a shared library item, so a duplicated block references
     * the same content rather than cloning it.
     *
     * @return array<string, mixed>
     */
    public function duplicateContent(LessonBlock $original, LessonBlock $copy): array
    {
        return $original->content;
    }
}
