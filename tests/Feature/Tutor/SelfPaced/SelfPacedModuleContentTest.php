<?php

namespace Tests\Feature\Tutor\SelfPaced;

use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithH5pLibrary;
use Tests\TestCase;

class SelfPacedModuleContentTest extends TestCase
{
    use InteractsWithH5pLibrary, RefreshDatabase;

    private function tutorWithCourse(): array
    {
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $course = $tutorUser->tutorProfile->selfPacedCourses()->create(['title' => 'Course']);

        return [$tutorUser, $course];
    }

    /**
     * Like tutorWithCourse(), but the course carries a real Subject+Grade
     * for the h5p activity eligibility tests below (StoreSelfPacedActivityRequest
     * matches an h5p activity's content against these).
     */
    private function tutorWithClassifiedCourse(string $subjectName = 'Mathematics'): array
    {
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $subject = Subject::create(['name' => $subjectName, 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $course = $tutorUser->tutorProfile->selfPacedCourses()->create([
            'title' => 'Course', 'subject_id' => $subject->id, 'grade_id' => $grade->id,
        ]);

        return [$tutorUser, $course, $subject, $grade];
    }

    public function test_tutor_can_create_and_reorder_modules(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        Sanctum::actingAs($tutor);

        $first = $this->postJson("/api/tutor/self-paced-courses/{$course->id}/modules", ['title' => 'Introduction'])
            ->assertCreated()->json('module');
        $second = $this->postJson("/api/tutor/self-paced-courses/{$course->id}/modules", ['title' => 'Advanced'])
            ->assertCreated()->json('module');

        $this->assertSame(0, $first['position']);
        $this->assertSame(1, $second['position']);

        $response = $this->patchJson("/api/tutor/self-paced-courses/{$course->id}/modules/reorder", [
            'module_ids' => [$second['id'], $first['id']],
        ]);

        $response->assertOk();
        $response->assertJsonPath('modules.0.id', $second['id']);
        $response->assertJsonPath('modules.1.id', $first['id']);
    }

    public function test_tutor_can_add_a_rich_text_activity(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $module = $course->modules()->create(['title' => 'Introduction', 'position' => 0]);
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/self-paced-modules/{$module->id}/activities", [
            'type' => 'rich_text',
            'title' => 'Welcome',
            'content' => ['html' => '<p>Hello</p>'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('activity.type', 'rich_text');
        $response->assertJsonPath('activity.has_required_content', true);
    }

    public function test_tutor_can_add_an_h5p_activity_matching_the_courses_subject_and_grade(): void
    {
        [$tutor, $course, $subject, $grade] = $this->tutorWithClassifiedCourse();
        $module = $course->modules()->create(['title' => 'Introduction', 'position' => 0]);
        $this->seedH5pContent(123, 'Interactive Quiz');
        $this->seedH5pClassification(123, $tutor->tutorProfile->id, $grade->id, $subject->id, Curriculum::create(['name' => 'CAPS', 'is_active' => true])->id);
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/self-paced-modules/{$module->id}/activities", [
            'type' => 'h5p',
            'title' => 'Interactive Activity',
            'content' => ['h5p_content_id' => '123'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('activity.type', 'h5p');
        $response->assertJsonPath('activity.has_required_content', true);
    }

    public function test_tutor_cannot_add_an_h5p_activity_with_mismatched_grade(): void
    {
        [$tutor, $course, $subject, $grade] = $this->tutorWithClassifiedCourse();
        $module = $course->modules()->create(['title' => 'Introduction', 'position' => 0]);
        $otherGrade = Grade::create(['name' => 'Grade 11', 'level' => 11, 'is_active' => true]);
        $this->seedH5pContent(123, 'Interactive Quiz');
        $this->seedH5pClassification(123, $tutor->tutorProfile->id, $otherGrade->id, $subject->id, Curriculum::create(['name' => 'CAPS', 'is_active' => true])->id);
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/self-paced-modules/{$module->id}/activities", [
            'type' => 'h5p',
            'title' => 'Interactive Activity',
            'content' => ['h5p_content_id' => '123'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('content');
    }

    public function test_tutor_cannot_add_an_h5p_activity_referencing_another_tutors_content(): void
    {
        [$tutor, $course, $subject, $grade] = $this->tutorWithClassifiedCourse();
        $module = $course->modules()->create(['title' => 'Introduction', 'position' => 0]);

        $owner = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $owner->id, 'display_name' => 'Owner']);

        $this->seedH5pContent(123, 'Interactive Quiz');
        $this->seedH5pClassification(123, $owner->tutorProfile->id, $grade->id, $subject->id, Curriculum::create(['name' => 'CAPS', 'is_active' => true])->id);
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/self-paced-modules/{$module->id}/activities", [
            'type' => 'h5p',
            'title' => 'Interactive Activity',
            'content' => ['h5p_content_id' => '123'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('content');
    }

    public function test_tutor_can_create_h5p_content_for_a_self_paced_course_even_without_an_approved_subject(): void
    {
        [$tutor, $course, $subject, $grade] = $this->tutorWithClassifiedCourse();
        $curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $this->seedH5pLibrary();
        // No TutorSubject/tutor_subject_grades approval exists for this
        // tutor+subject at all — the self_paced_course_id anchor should
        // still be accepted, same as the lesson_block_id case.
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/h5p-content', [
            'library' => 'H5P.MultiChoice 1.16',
            'params' => [
                'params' => ['question' => 'What is 2 + 2?'],
                'metadata' => ['title' => 'Sample Question'],
            ],
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'curriculum_id' => $curriculum->id,
            'self_paced_course_id' => $course->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('h5p_content_classifications', [
            'h5p_content_id' => $response->json('id'),
            'tutor_profile_id' => $tutor->tutorProfile->id,
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'curriculum_id' => $curriculum->id,
        ]);
    }

    public function test_tutor_cannot_create_h5p_content_for_a_self_paced_course_with_mismatched_grade(): void
    {
        [$tutor, $course, $subject] = $this->tutorWithClassifiedCourse();
        $otherGrade = Grade::create(['name' => 'Grade 11', 'level' => 11, 'is_active' => true]);
        $curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $this->seedH5pLibrary();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/h5p-content', [
            'library' => 'H5P.MultiChoice 1.16',
            'params' => [
                'params' => ['question' => 'What is 2 + 2?'],
                'metadata' => ['title' => 'Sample Question'],
            ],
            'grade_id' => $otherGrade->id,
            'subject_id' => $subject->id,
            'curriculum_id' => $curriculum->id,
            'self_paced_course_id' => $course->id,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('grade_id');
    }

    public function test_tutor_can_add_a_video_activity_with_an_uploaded_attachment(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $module = $course->modules()->create(['title' => 'Introduction', 'position' => 0]);
        Sanctum::actingAs($tutor);

        $createResponse = $this->postJson("/api/tutor/self-paced-modules/{$module->id}/activities", [
            'type' => 'video',
            'title' => 'Welcome Video',
        ]);
        $createResponse->assertCreated();
        // The frontend reads activity.attachments straight off the create
        // response to seed its local state — it must be an empty array,
        // never an omitted key (regression: store() wasn't eager-loading
        // the relation, so whenLoaded() dropped the field entirely).
        $createResponse->assertJsonPath('activity.attachments', []);
        $activity = $createResponse->json('activity');

        $this->assertFalse($activity['has_required_content']);

        $response = $this->postJson("/api/tutor/self-paced-activities/{$activity['id']}/attachments", [
            'media_type' => 'video_upload',
            'title' => 'Welcome Video',
            'file' => UploadedFile::fake()->create('welcome.mp4', 1000, 'video/mp4'),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('attachment.media_type', 'video_upload');
    }

    public function test_tutor_can_add_an_assessment_with_a_surveyjs_provider(): void
    {
        [$tutor, $course, $subject, $grade] = $this->tutorWithClassifiedCourse();
        $module = $course->modules()->create(['title' => 'Assessments', 'position' => 0]);
        Sanctum::actingAs($tutor);

        $surveyContent = $tutor->tutorProfile->selfPacedSurveyContents()->create([
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'curriculum_id' => Curriculum::create(['name' => 'CAPS', 'is_active' => true])->id,
            'title' => 'Chapter 1 Bank',
        ]);

        $response = $this->postJson("/api/tutor/self-paced-modules/{$module->id}/assessments", [
            'assessment_type' => 'knowledge_check',
            'title' => 'Quick Check',
            'passing_score' => 70,
            'attempts_mode' => 'limited',
            'max_attempts' => 2,
            'provider' => 'surveyjs',
            'provider_config' => ['survey_content_id' => $surveyContent->id],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('assessment.is_configured', true);
    }

    public function test_tutor_cannot_add_an_assessment_with_a_surveyjs_content_of_a_different_subject(): void
    {
        [$tutor, $course, , $grade] = $this->tutorWithClassifiedCourse();
        $module = $course->modules()->create(['title' => 'Assessments', 'position' => 0]);
        Sanctum::actingAs($tutor);

        $surveyContent = $tutor->tutorProfile->selfPacedSurveyContents()->create([
            'grade_id' => $grade->id,
            'subject_id' => Subject::create(['name' => 'Physics', 'is_active' => true])->id,
            'curriculum_id' => Curriculum::create(['name' => 'CAPS', 'is_active' => true])->id,
            'title' => 'Physics Bank',
        ]);

        $response = $this->postJson("/api/tutor/self-paced-modules/{$module->id}/assessments", [
            'assessment_type' => 'knowledge_check',
            'title' => 'Quick Check',
            'provider' => 'surveyjs',
            'provider_config' => ['survey_content_id' => $surveyContent->id],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('provider_config');
    }

    public function test_tutor_can_add_an_assessment_with_a_matching_h5p_provider(): void
    {
        [$tutor, $course, $subject, $grade] = $this->tutorWithClassifiedCourse();
        $module = $course->modules()->create(['title' => 'Assessments', 'position' => 0]);
        $curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $this->seedH5pContent(456, 'Interactive Quiz');
        $this->seedH5pClassification(456, $tutor->tutorProfile->id, $grade->id, $subject->id, $curriculum->id);
        $tutor->tutorProfile->selfPacedH5pContents()->create(['h5p_content_id' => '456', 'title' => 'Interactive Quiz']);
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/self-paced-modules/{$module->id}/assessments", [
            'assessment_type' => 'knowledge_check',
            'title' => 'Quick Check',
            'provider' => 'h5p',
            'provider_config' => ['h5p_content_id' => '456'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('assessment.is_configured', true);
    }

    public function test_tutor_cannot_add_an_assessment_with_an_h5p_provider_of_a_different_grade(): void
    {
        [$tutor, $course, $subject] = $this->tutorWithClassifiedCourse();
        $module = $course->modules()->create(['title' => 'Assessments', 'position' => 0]);
        $curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $otherGrade = Grade::create(['name' => 'Grade 11', 'level' => 11, 'is_active' => true]);
        $this->seedH5pContent(789, 'Interactive Quiz');
        $this->seedH5pClassification(789, $tutor->tutorProfile->id, $otherGrade->id, $subject->id, $curriculum->id);
        $tutor->tutorProfile->selfPacedH5pContents()->create(['h5p_content_id' => '789', 'title' => 'Interactive Quiz']);
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/self-paced-modules/{$module->id}/assessments", [
            'assessment_type' => 'knowledge_check',
            'title' => 'Quick Check',
            'provider' => 'h5p',
            'provider_config' => ['h5p_content_id' => '789'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('provider_config');
    }

    public function test_assessment_with_limited_attempts_requires_max_attempts(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $module = $course->modules()->create(['title' => 'Assessments', 'position' => 0]);
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/self-paced-modules/{$module->id}/assessments", [
            'assessment_type' => 'knowledge_check',
            'title' => 'Quick Check',
            'attempts_mode' => 'limited',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('max_attempts');
    }

    public function test_activities_and_assessments_share_a_single_reorderable_position_sequence(): void
    {
        [$tutor, $course] = $this->tutorWithCourse();
        $module = $course->modules()->create(['title' => 'Mixed', 'position' => 0]);
        Sanctum::actingAs($tutor);

        $video = $this->postJson("/api/tutor/self-paced-modules/{$module->id}/activities", [
            'type' => 'video', 'title' => 'Video',
        ])->json('activity');

        $quiz = $this->postJson("/api/tutor/self-paced-modules/{$module->id}/assessments", [
            'assessment_type' => 'practice_quiz', 'title' => 'Quiz',
        ])->json('assessment');

        $reading = $this->postJson("/api/tutor/self-paced-modules/{$module->id}/activities", [
            'type' => 'reading', 'title' => 'Reading', 'content' => ['instructions' => 'Read chapter 1'],
        ])->json('activity');

        $this->assertSame([0, 1, 2], [$video['position'], $quiz['position'], $reading['position']]);

        // Reorder to: Reading, Video, Quiz
        $response = $this->patchJson("/api/tutor/self-paced-modules/{$module->id}/content/reorder", [
            'items' => [
                ['type' => 'activity', 'id' => $reading['id']],
                ['type' => 'activity', 'id' => $video['id']],
                ['type' => 'assessment', 'id' => $quiz['id']],
            ],
        ]);

        $response->assertOk();

        $ordered = collect($response->json('module.activities'))
            ->concat($response->json('module.assessments'))
            ->sortBy('position')
            ->pluck('title')
            ->values()
            ->all();

        $this->assertSame(['Reading', 'Video', 'Quiz'], $ordered);
    }

    public function test_tutor_cannot_manage_another_tutors_module_or_activity(): void
    {
        [$owner, $course] = $this->tutorWithCourse();
        $module = $course->modules()->create(['title' => 'Introduction', 'position' => 0]);
        $activity = $module->activities()->create(['type' => 'rich_text', 'title' => 'Welcome', 'position' => 0]);

        $intruder = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $intruder->id, 'display_name' => 'Intruder']);
        Sanctum::actingAs($intruder);

        $this->putJson("/api/tutor/self-paced-modules/{$module->id}", ['title' => 'Hacked'])->assertForbidden();
        $this->putJson("/api/tutor/self-paced-activities/{$activity->id}", ['title' => 'Hacked'])->assertForbidden();
        $this->postJson("/api/tutor/self-paced-modules/{$module->id}/activities", ['type' => 'reading', 'title' => 'X'])->assertForbidden();
    }
}
