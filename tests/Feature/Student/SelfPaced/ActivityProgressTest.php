<?php

namespace Tests\Feature\Student\SelfPaced;

use App\Models\ActivityProgress;
use App\Models\Enrollment;
use App\Models\SelfPacedCourse;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithH5pLibrary;
use Tests\TestCase;

class ActivityProgressTest extends TestCase
{
    use InteractsWithH5pLibrary, RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    private function courseWithTwoModules(TutorProfile $tutor): SelfPacedCourse
    {
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Test Course', 'price' => 100, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);

        $module1 = $course->modules()->create([
            'title' => 'Module 1', 'position' => 0,
            'activity_completion_required' => true, 'assessment_completion_required' => false,
        ]);
        $module1->activities()->create([
            'type' => 'rich_text', 'title' => 'Intro', 'position' => 0, 'required' => true,
            'content' => ['html' => '<p>Hi</p>'],
        ]);

        $module2 = $course->modules()->create([
            'title' => 'Module 2', 'position' => 1,
            'activity_completion_required' => true, 'assessment_completion_required' => false,
        ]);
        $module2->activities()->create([
            'type' => 'rich_text', 'title' => 'Next', 'position' => 0, 'required' => true,
            'content' => ['html' => '<p>Next</p>'],
        ]);

        return $course;
    }

    private function enroll(User $student, SelfPacedCourse $course): Enrollment
    {
        return Enrollment::create([
            'student_id' => $student->id, 'self_paced_course_id' => $course->id,
            'status' => 'active', 'enrolled_at' => now(),
        ]);
    }

    public function test_student_can_view_and_mark_an_activity_complete(): void
    {
        $tutor = $this->tutor();
        $course = $this->courseWithTwoModules($tutor);
        $student = User::factory()->create();
        $this->enroll($student, $course);
        $activity = $course->modules->first()->activities->first();

        Sanctum::actingAs($student);

        $show = $this->getJson("/api/student/self-paced-courses/{$course->id}/activities/{$activity->id}");
        $show->assertOk();
        $show->assertJsonPath('activity.completed', false);

        $complete = $this->patchJson("/api/student/self-paced-courses/{$course->id}/activities/{$activity->id}/complete");
        $complete->assertOk();

        $recheck = $this->getJson("/api/student/self-paced-courses/{$course->id}/activities/{$activity->id}");
        $recheck->assertJsonPath('activity.completed', true);
    }

    public function test_marking_an_activity_complete_is_idempotent(): void
    {
        $tutor = $this->tutor();
        $course = $this->courseWithTwoModules($tutor);
        $student = User::factory()->create();
        $this->enroll($student, $course);
        $activity = $course->modules->first()->activities->first();

        Sanctum::actingAs($student);

        $this->patchJson("/api/student/self-paced-courses/{$course->id}/activities/{$activity->id}/complete")->assertOk();
        $second = $this->patchJson("/api/student/self-paced-courses/{$course->id}/activities/{$activity->id}/complete");

        $second->assertOk();
        $this->assertSame(
            1,
            ActivityProgress::where('self_paced_activity_id', $activity->id)->count(),
        );
    }

    public function test_cannot_complete_an_activity_in_a_locked_module(): void
    {
        $tutor = $this->tutor();
        $course = $this->courseWithTwoModules($tutor);
        $student = User::factory()->create();
        $this->enroll($student, $course);
        $lockedActivity = $course->modules->last()->activities->first();

        Sanctum::actingAs($student);

        $response = $this->patchJson("/api/student/self-paced-courses/{$course->id}/activities/{$lockedActivity->id}/complete");

        $response->assertUnprocessable();
    }

    public function test_activity_from_another_course_returns_404(): void
    {
        $tutor = $this->tutor();
        $courseA = $this->courseWithTwoModules($tutor);
        $courseB = $this->courseWithTwoModules($tutor);
        $student = User::factory()->create();
        $this->enroll($student, $courseA);
        $this->enroll($student, $courseB);

        $activityFromB = $courseB->modules->first()->activities->first();

        Sanctum::actingAs($student);
        $response = $this->getJson("/api/student/self-paced-courses/{$courseA->id}/activities/{$activityFromB->id}");

        $response->assertNotFound();
    }

    public function test_student_can_fetch_the_h5p_player_model_for_an_h5p_activity(): void
    {
        $tutor = $this->tutor();
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'H5P Course', 'price' => 100, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);
        $module = $course->modules()->create([
            'title' => 'Module 1', 'position' => 0,
            'activity_completion_required' => true, 'assessment_completion_required' => false,
        ]);
        $activity = $module->activities()->create([
            'type' => 'h5p', 'title' => 'Interactive', 'position' => 0, 'required' => true,
            'content' => ['h5p_content_id' => '123'],
        ]);
        $student = User::factory()->create();
        $this->enroll($student, $course);
        $this->seedH5pContent(123);

        Sanctum::actingAs($student);
        $response = $this->getJson("/api/student/self-paced-courses/{$course->id}/activities/{$activity->id}/h5p-player-model");

        $response->assertOk();
        $response->assertJsonPath('integration.contents.cid-123.library', 'H5P.MultiChoice 1.16');
    }

    public function test_h5p_player_model_is_not_available_for_a_non_h5p_activity(): void
    {
        $tutor = $this->tutor();
        $course = $this->courseWithTwoModules($tutor);
        $student = User::factory()->create();
        $this->enroll($student, $course);
        $activity = $course->modules->first()->activities->first();

        Sanctum::actingAs($student);
        $response = $this->getJson("/api/student/self-paced-courses/{$course->id}/activities/{$activity->id}/h5p-player-model");

        $response->assertNotFound();
    }

    public function test_non_enrolled_student_cannot_fetch_the_activity_h5p_player_model(): void
    {
        $tutor = $this->tutor();
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'H5P Course', 'price' => 100, 'currency' => 'ZAR',
            'status' => 'published', 'visibility' => 'public',
        ]);
        $module = $course->modules()->create([
            'title' => 'Module 1', 'position' => 0,
            'activity_completion_required' => true, 'assessment_completion_required' => false,
        ]);
        $activity = $module->activities()->create([
            'type' => 'h5p', 'title' => 'Interactive', 'position' => 0, 'required' => true,
            'content' => ['h5p_content_id' => '123'],
        ]);
        $student = User::factory()->create();

        Sanctum::actingAs($student);
        $response = $this->getJson("/api/student/self-paced-courses/{$course->id}/activities/{$activity->id}/h5p-player-model");

        $response->assertForbidden();
    }
}
