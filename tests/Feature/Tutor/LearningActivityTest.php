<?php

namespace Tests\Feature\Tutor;

use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LearningActivityTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private Curriculum $curriculum;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
    }

    private function tutorWithActivity(): array
    {
        $user = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['user_id' => $user->id]);

        $tutorSubject = TutorSubject::create([
            'tutor_profile_id' => $tutorProfile->id,
            'subject_id' => $this->subject->id,
            'status' => 'approved',
        ]);
        $tutorSubject->tutorSubjectGrades()->create(['grade_id' => $this->grade->id]);

        $course = $tutorProfile->courses()->create([
            'curriculum_id' => $this->curriculum->id,
            'grade_id' => $this->grade->id,
            'subject_id' => $this->subject->id,
            'title' => 'Algebra Fundamentals',
            'description' => 'A complete introduction to algebraic concepts.',
            'estimated_duration_minutes' => 120,
            'difficulty' => 'beginner',
            'language' => 'English',
            'status' => 'draft',
        ]);
        $chapter = $course->chapters()->create(['title' => 'Introduction', 'position' => 0, 'status' => 'draft']);
        $lesson = $chapter->lessons()->create(['title' => 'What is Algebra?', 'position' => 0, 'status' => 'draft']);
        $activity = $lesson->learningActivities()->create([
            'type' => 'assignment',
            'title' => 'Untitled Assignment',
            'status' => 'draft',
            'submission_type' => 'text',
        ]);

        return [$user->fresh(), $activity];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Essay: The French Revolution',
            'description' => 'Write a 1000-word essay.',
            'instructions_html' => '<p>Follow the rubric.</p>',
            'instructions_json' => ['type' => 'doc', 'content' => []],
            'status' => 'draft',
            'submission_type' => 'text',
            'max_score' => 100,
        ], $overrides);
    }

    public function test_tutor_can_view_own_activity(): void
    {
        [$tutor, $activity] = $this->tutorWithActivity();
        Sanctum::actingAs($tutor);

        $response = $this->getJson("/api/tutor/learning-activities/{$activity->id}");

        $response->assertOk()->assertJsonPath('activity.id', $activity->id);
    }

    public function test_tutor_can_update_an_activity(): void
    {
        [$tutor, $activity] = $this->tutorWithActivity();
        Sanctum::actingAs($tutor);

        $response = $this->putJson("/api/tutor/learning-activities/{$activity->id}", $this->validPayload());

        $response->assertOk();
        $response->assertJsonPath('activity.title', 'Essay: The French Revolution');
        $response->assertJsonPath('activity.max_score', '100.00');
    }

    public function test_external_activity_requires_a_valid_external_url(): void
    {
        $user = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['user_id' => $user->id]);

        $tutorSubject = TutorSubject::create([
            'tutor_profile_id' => $tutorProfile->id,
            'subject_id' => $this->subject->id,
            'status' => 'approved',
        ]);
        $tutorSubject->tutorSubjectGrades()->create(['grade_id' => $this->grade->id]);

        $course = $tutorProfile->courses()->create([
            'curriculum_id' => $this->curriculum->id,
            'grade_id' => $this->grade->id,
            'subject_id' => $this->subject->id,
            'title' => 'Algebra Fundamentals',
            'description' => 'A complete introduction to algebraic concepts.',
            'estimated_duration_minutes' => 120,
            'difficulty' => 'beginner',
            'language' => 'English',
            'status' => 'draft',
        ]);
        $chapter = $course->chapters()->create(['title' => 'Introduction', 'position' => 0, 'status' => 'draft']);
        $lesson = $chapter->lessons()->create(['title' => 'What is Algebra?', 'position' => 0, 'status' => 'draft']);
        $activity = $lesson->learningActivities()->create([
            'type' => 'external_activity',
            'title' => 'Watch the video',
            'status' => 'draft',
            'submission_type' => 'none',
        ]);

        Sanctum::actingAs($user->fresh());

        $missing = $this->putJson("/api/tutor/learning-activities/{$activity->id}", $this->validPayload());
        $missing->assertUnprocessable()->assertJsonValidationErrors('settings.external_url');

        $valid = $this->putJson("/api/tutor/learning-activities/{$activity->id}", $this->validPayload([
            'settings' => ['external_url' => 'https://example.com/video'],
        ]));
        $valid->assertOk();
        $valid->assertJsonPath('activity.settings.external_url', 'https://example.com/video');
    }

    public function test_tutor_cannot_update_another_tutors_activity(): void
    {
        [$owner, $activity] = $this->tutorWithActivity();
        [$other] = $this->tutorWithActivity();

        Sanctum::actingAs($other);

        $response = $this->putJson("/api/tutor/learning-activities/{$activity->id}", $this->validPayload());

        $response->assertForbidden();
    }

    public function test_tutor_can_delete_an_activity(): void
    {
        [$tutor, $activity] = $this->tutorWithActivity();
        Sanctum::actingAs($tutor);

        $response = $this->deleteJson("/api/tutor/learning-activities/{$activity->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('learning_activities', ['id' => $activity->id]);
    }
}
