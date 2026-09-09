<?php

namespace Tests\Feature\Tutor\SelfPaced;

use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SelfPacedSurveyContentTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private Grade $grade;

    private Curriculum $curriculum;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $this->curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
    }

    private function tutor(): User
    {
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        return $tutorUser;
    }

    public function test_tutor_can_create_a_survey_content_tagged_by_grade_subject_and_curriculum(): void
    {
        $tutor = $this->tutor();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/self-paced-survey-contents', [
            'title' => 'Algebra Basics Bank',
            'grade_id' => $this->grade->id,
            'subject_id' => $this->subject->id,
            'curriculum_id' => $this->curriculum->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('survey_content.title', 'Algebra Basics Bank');
        $response->assertJsonPath('survey_content.grade.id', $this->grade->id);
        $response->assertJsonPath('survey_content.subject.id', $this->subject->id);
        $response->assertJsonPath('survey_content.curriculum.id', $this->curriculum->id);
    }

    public function test_tutor_can_add_and_reorder_questions(): void
    {
        $tutor = $this->tutor();
        $surveyContent = $tutor->tutorProfile->selfPacedSurveyContents()->create([
            'grade_id' => $this->grade->id,
            'subject_id' => $this->subject->id,
            'curriculum_id' => $this->curriculum->id,
            'title' => 'Bank',
        ]);
        Sanctum::actingAs($tutor);

        $first = $this->postJson("/api/tutor/self-paced-survey-contents/{$surveyContent->id}/questions", [
            'type' => 'radiogroup',
            'text' => 'What is 2 + 2?',
            'choices' => ['3', '4', '5'],
            'correct_answer' => '4',
            'points' => 1,
        ])->assertCreated()->json('question');

        $second = $this->postJson("/api/tutor/self-paced-survey-contents/{$surveyContent->id}/questions", [
            'type' => 'boolean',
            'text' => 'The sky is blue.',
            'correct_answer' => true,
            'points' => 1,
        ])->assertCreated()->json('question');

        $this->assertSame('4', $first['correct_answer']);
        $this->assertSame([0, 1], [$first['position'], $second['position']]);

        $response = $this->patchJson("/api/tutor/self-paced-survey-contents/{$surveyContent->id}/questions/reorder", [
            'question_ids' => [$second['id'], $first['id']],
        ]);

        $response->assertOk();
        $response->assertJsonPath('questions.0.id', $second['id']);
        $response->assertJsonPath('questions.1.id', $first['id']);
    }

    public function test_choice_question_requires_at_least_two_choices(): void
    {
        $tutor = $this->tutor();
        $surveyContent = $tutor->tutorProfile->selfPacedSurveyContents()->create([
            'grade_id' => $this->grade->id,
            'subject_id' => $this->subject->id,
            'curriculum_id' => $this->curriculum->id,
            'title' => 'Bank',
        ]);
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/self-paced-survey-contents/{$surveyContent->id}/questions", [
            'type' => 'radiogroup',
            'text' => 'Pick one',
            'choices' => ['only one'],
            'correct_answer' => 'only one',
            'points' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('choices');
    }

    public function test_index_filters_by_grade_subject_and_curriculum(): void
    {
        $tutor = $this->tutor();
        $otherGrade = Grade::create(['name' => 'Grade 11', 'level' => 11, 'is_active' => true]);

        $tutor->tutorProfile->selfPacedSurveyContents()->create([
            'grade_id' => $this->grade->id, 'subject_id' => $this->subject->id, 'curriculum_id' => $this->curriculum->id, 'title' => 'Matches',
        ]);
        $tutor->tutorProfile->selfPacedSurveyContents()->create([
            'grade_id' => $otherGrade->id, 'subject_id' => $this->subject->id, 'curriculum_id' => $this->curriculum->id, 'title' => 'Different Grade',
        ]);

        Sanctum::actingAs($tutor);
        $response = $this->getJson('/api/tutor/self-paced-survey-contents?'.http_build_query(['grade_id' => $this->grade->id]));

        $response->assertOk();
        $response->assertJsonCount(1, 'survey_contents');
        $response->assertJsonPath('survey_contents.0.title', 'Matches');
    }

    public function test_tutor_cannot_manage_another_tutors_survey_content(): void
    {
        $owner = $this->tutor();
        $surveyContent = $owner->tutorProfile->selfPacedSurveyContents()->create([
            'grade_id' => $this->grade->id, 'subject_id' => $this->subject->id, 'curriculum_id' => $this->curriculum->id, 'title' => 'Owned',
        ]);

        $intruder = $this->tutor();
        Sanctum::actingAs($intruder);

        $this->getJson("/api/tutor/self-paced-survey-contents/{$surveyContent->id}")->assertForbidden();
        $this->putJson("/api/tutor/self-paced-survey-contents/{$surveyContent->id}", ['title' => 'Hacked'])->assertForbidden();
        $this->postJson("/api/tutor/self-paced-survey-contents/{$surveyContent->id}/questions", [
            'type' => 'text', 'text' => 'X', 'points' => 1,
        ])->assertForbidden();
    }

    public function test_assessment_rejects_a_survey_content_id_belonging_to_another_tutor(): void
    {
        $owner = $this->tutor();
        $surveyContent = $owner->tutorProfile->selfPacedSurveyContents()->create([
            'grade_id' => $this->grade->id, 'subject_id' => $this->subject->id, 'curriculum_id' => $this->curriculum->id, 'title' => 'Owned',
        ]);

        $intruder = $this->tutor();
        $course = $intruder->tutorProfile->selfPacedCourses()->create(['title' => 'Intruder Course']);
        $module = $course->modules()->create(['title' => 'Module', 'position' => 0]);
        Sanctum::actingAs($intruder);

        $response = $this->postJson("/api/tutor/self-paced-modules/{$module->id}/assessments", [
            'assessment_type' => 'practice_quiz',
            'title' => 'Quiz',
            'provider' => 'surveyjs',
            'provider_config' => ['survey_content_id' => $surveyContent->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('provider_config');
    }
}
