<?php

namespace Tests\Feature\Tutor;

use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the `service_id` filter on the courses/chapters/lessons index
 * endpoints — used by the "Choose Lesson" picker when scheduling a
 * session, so a tutor only ever sees published lessons actually
 * assignable to the booking's service, per Course::matchesService().
 */
class LessonPickerFilteringTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): User
    {
        $user = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $user->id, 'display_name' => 'Test Tutor']);

        return $user->fresh();
    }

    private function approveSubjectForGrades(User $tutor, Subject $subject, array $grades): void
    {
        $tutorSubject = TutorSubject::create([
            'tutor_profile_id' => $tutor->tutorProfile->id,
            'subject_id' => $subject->id,
            'status' => 'approved',
        ]);

        foreach ($grades as $grade) {
            $tutorSubject->tutorSubjectGrades()->create(['grade_id' => $grade->id]);
        }
    }

    private function course(User $tutor, Subject $subject, Curriculum $curriculum, Grade $grade, array $overrides = []): int
    {
        $course = $tutor->tutorProfile->courses()->create(array_merge([
            'curriculum_id' => $curriculum->id,
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'title' => 'Test Course',
            'description' => 'A course.',
            'estimated_duration_minutes' => 60,
            'difficulty' => 'beginner',
            'language' => 'English',
            'status' => 'published',
        ], $overrides));

        return $course->id;
    }

    private function service(User $tutor, Subject $subject, Curriculum $curriculum, ?Grade $grade): Service
    {
        $category = ServiceCategory::firstOrCreate(['name' => 'Private Lesson'], ['is_active' => true]);
        $format = SessionFormat::firstOrCreate(['name' => 'Online'], ['is_active' => true]);

        $service = $tutor->tutorProfile->services()->create([
            'subject_id' => $subject->id,
            'grade_id' => $grade?->id,
            'service_category_id' => $category->id,
            'session_format_id' => $format->id,
            'title' => 'Test Service',
            'description' => 'A service.',
            'price' => 300,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'published',
        ]);
        $service->curricula()->attach($curriculum->id);

        return $service;
    }

    public function test_service_id_filters_out_courses_with_a_different_subject_or_curriculum(): void
    {
        $tutor = $this->tutor();
        $subject = Subject::create(['name' => 'Life Sciences', 'is_active' => true]);
        $otherSubject = Subject::create(['name' => 'Geography', 'is_active' => true]);
        $curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $otherCurriculum = Curriculum::create(['name' => 'IEB', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 11', 'level' => 11, 'is_active' => true]);

        $this->approveSubjectForGrades($tutor, $subject, [$grade]);
        $this->approveSubjectForGrades($tutor, $otherSubject, [$grade]);

        $matching = $this->course($tutor, $subject, $curriculum, $grade, ['title' => 'Matching Course']);
        $this->course($tutor, $otherSubject, $curriculum, $grade, ['title' => 'Wrong Subject']);
        $this->course($tutor, $subject, $otherCurriculum, $grade, ['title' => 'Wrong Curriculum']);

        $service = $this->service($tutor, $subject, $curriculum, $grade);

        Sanctum::actingAs($tutor);

        $response = $this->getJson("/api/tutor/courses?service_id={$service->id}");

        $response->assertOk()->assertJsonCount(1, 'courses');
        $response->assertJsonPath('courses.0.id', $matching);
    }

    public function test_service_id_excludes_draft_courses(): void
    {
        $tutor = $this->tutor();
        $subject = Subject::create(['name' => 'Life Sciences', 'is_active' => true]);
        $curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 11', 'level' => 11, 'is_active' => true]);
        $this->approveSubjectForGrades($tutor, $subject, [$grade]);

        $this->course($tutor, $subject, $curriculum, $grade, ['status' => 'draft']);
        $service = $this->service($tutor, $subject, $curriculum, $grade);

        Sanctum::actingAs($tutor);

        $response = $this->getJson("/api/tutor/courses?service_id={$service->id}");

        $response->assertOk()->assertJsonCount(0, 'courses');
    }

    public function test_service_with_no_grade_requirement_matches_a_course_of_any_grade(): void
    {
        $tutor = $this->tutor();
        $subject = Subject::create(['name' => 'Life Sciences', 'is_active' => true]);
        $curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 11', 'level' => 11, 'is_active' => true]);
        $this->approveSubjectForGrades($tutor, $subject, [$grade]);

        $matching = $this->course($tutor, $subject, $curriculum, $grade);
        // A service with grade_id null (no specific grade requirement) —
        // previously this could never match any course, since a course's
        // own grade_id is never null and the comparison was a strict ===.
        $service = $this->service($tutor, $subject, $curriculum, null);

        Sanctum::actingAs($tutor);

        $response = $this->getJson("/api/tutor/courses?service_id={$service->id}");

        $response->assertOk()->assertJsonCount(1, 'courses');
        $response->assertJsonPath('courses.0.id', $matching);
    }

    public function test_service_with_a_specific_grade_excludes_a_course_for_a_different_grade(): void
    {
        $tutor = $this->tutor();
        $subject = Subject::create(['name' => 'Life Sciences', 'is_active' => true]);
        $curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 11', 'level' => 11, 'is_active' => true]);
        $otherGrade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $this->approveSubjectForGrades($tutor, $subject, [$grade, $otherGrade]);

        $this->course($tutor, $subject, $curriculum, $otherGrade);
        $service = $this->service($tutor, $subject, $curriculum, $grade);

        Sanctum::actingAs($tutor);

        $response = $this->getJson("/api/tutor/courses?service_id={$service->id}");

        $response->assertOk()->assertJsonCount(0, 'courses');
    }

    public function test_without_service_id_all_of_the_tutors_courses_are_returned_regardless_of_status(): void
    {
        $tutor = $this->tutor();
        $subject = Subject::create(['name' => 'Life Sciences', 'is_active' => true]);
        $curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 11', 'level' => 11, 'is_active' => true]);
        $this->approveSubjectForGrades($tutor, $subject, [$grade]);

        $this->course($tutor, $subject, $curriculum, $grade, ['status' => 'draft']);
        $this->course($tutor, $subject, $curriculum, $grade, ['status' => 'published']);

        Sanctum::actingAs($tutor);

        $response = $this->getJson('/api/tutor/courses');

        $response->assertOk()->assertJsonCount(2, 'courses');
    }

    public function test_service_id_for_another_tutors_service_is_rejected(): void
    {
        $tutor = $this->tutor();
        $otherTutor = $this->tutor();
        $subject = Subject::create(['name' => 'Life Sciences', 'is_active' => true]);
        $curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 11', 'level' => 11, 'is_active' => true]);
        $this->approveSubjectForGrades($otherTutor, $subject, [$grade]);

        $otherService = $this->service($otherTutor, $subject, $curriculum, $grade);

        Sanctum::actingAs($tutor);

        $response = $this->getJson("/api/tutor/courses?service_id={$otherService->id}");

        $response->assertNotFound();
    }

    public function test_service_id_limits_chapters_and_lessons_to_published_only(): void
    {
        $tutor = $this->tutor();
        $subject = Subject::create(['name' => 'Life Sciences', 'is_active' => true]);
        $curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 11', 'level' => 11, 'is_active' => true]);
        $this->approveSubjectForGrades($tutor, $subject, [$grade]);

        $courseId = $this->course($tutor, $subject, $curriculum, $grade);
        $course = Course::findOrFail($courseId);

        $publishedChapter = $course->chapters()->create(['title' => 'Published Chapter', 'position' => 0, 'status' => 'published']);
        $course->chapters()->create(['title' => 'Draft Chapter', 'position' => 1, 'status' => 'draft']);

        $publishedLesson = $publishedChapter->lessons()->create(['title' => 'Published Lesson', 'position' => 0, 'status' => 'published']);
        $publishedChapter->lessons()->create(['title' => 'Draft Lesson', 'position' => 1, 'status' => 'draft']);

        $service = $this->service($tutor, $subject, $curriculum, $grade);

        Sanctum::actingAs($tutor);

        $chapters = $this->getJson("/api/tutor/courses/{$courseId}/chapters?service_id={$service->id}");
        $chapters->assertOk()->assertJsonCount(1, 'chapters');
        $chapters->assertJsonPath('chapters.0.id', $publishedChapter->id);

        $lessons = $this->getJson("/api/tutor/chapters/{$publishedChapter->id}/lessons?service_id={$service->id}");
        $lessons->assertOk()->assertJsonCount(1, 'lessons');
        $lessons->assertJsonPath('lessons.0.id', $publishedLesson->id);
    }
}
