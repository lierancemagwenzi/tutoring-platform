<?php

namespace App\Services\SelfPaced;

use App\Enums\SelfPacedCourseStatus;
use App\Models\SelfPacedCourse;
use App\Models\TutorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SelfPacedCourseService
{
    public function create(TutorProfile $tutor, array $data): SelfPacedCourse
    {
        // fresh() so DB-side defaults (status, visibility, ...) that the
        // Store request never sends are reflected immediately, not just
        // after the next fetch.
        return $tutor->selfPacedCourses()->create($data)->fresh();
    }

    public function update(SelfPacedCourse $course, array $data): SelfPacedCourse
    {
        if (isset($data['thumbnail']) && $data['thumbnail'] instanceof UploadedFile) {
            if ($course->thumbnail_path) {
                Storage::disk('public')->delete($course->thumbnail_path);
            }
            $data['thumbnail_path'] = $data['thumbnail']->store('self-paced-courses/thumbnails', 'public');
        }
        unset($data['thumbnail']);

        if (isset($data['promo_video']) && $data['promo_video'] instanceof UploadedFile) {
            if ($course->promo_video_path && ! str_starts_with($course->promo_video_path, 'http')) {
                Storage::disk('public')->delete($course->promo_video_path);
            }
            $data['promo_video_path'] = $data['promo_video']->store('self-paced-courses/promo-videos', 'public');
        }
        unset($data['promo_video']);

        $course->update($data);

        return $course->fresh();
    }

    public function publish(SelfPacedCourse $course): SelfPacedCourse
    {
        $course->update(['status' => SelfPacedCourseStatus::Published]);

        return $course->fresh();
    }

    public function unpublish(SelfPacedCourse $course): SelfPacedCourse
    {
        $course->update(['status' => SelfPacedCourseStatus::Draft]);

        return $course->fresh();
    }

    public function makePrivate(SelfPacedCourse $course): SelfPacedCourse
    {
        $course->update(['status' => SelfPacedCourseStatus::PrivateStatus]);

        return $course->fresh();
    }

    public function archive(SelfPacedCourse $course): SelfPacedCourse
    {
        $course->update(['status' => SelfPacedCourseStatus::Archived]);

        return $course->fresh();
    }

    public function delete(SelfPacedCourse $course): void
    {
        if ($course->thumbnail_path) {
            Storage::disk('public')->delete($course->thumbnail_path);
        }

        if ($course->promo_video_path && ! str_starts_with($course->promo_video_path, 'http')) {
            Storage::disk('public')->delete($course->promo_video_path);
        }

        $course->delete();
    }
}
