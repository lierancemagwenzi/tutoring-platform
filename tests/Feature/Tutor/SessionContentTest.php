<?php

namespace Tests\Feature\Tutor;

use App\Models\AvailabilityDate;
use App\Models\AvailabilitySlot;
use App\Models\Booking;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TeachingSession;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\Booking\BookingConfirmationService;
use App\Services\Commerce\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionContentTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private Curriculum $curriculum;

    private Grade $grade;

    private ServiceCategory $category;

    private SessionFormat $onlineFormat;

    private string $futureDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $this->category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $this->onlineFormat = SessionFormat::create(['name' => 'Online', 'is_active' => true]);
        $this->futureDate = Carbon::now()->addDays(10)->format('Y-m-d');
    }

    /**
     * @return array{0: User, 1: TutorProfile, 2: Service, 3: AvailabilitySlot}
     */
    private function createTutorWithService(): array
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        $service = $tutorProfile->services()->create([
            'subject_id' => $this->subject->id,
            'grade_id' => $this->grade->id,
            'service_category_id' => $this->category->id,
            'session_format_id' => $this->onlineFormat->id,
            'title' => 'Grade 12 Maths',
            'description' => 'Exam preparation.',
            'price' => 300,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 2,
            'visibility' => 'published',
        ]);
        $service->curricula()->attach($this->curriculum->id);

        $availabilityDate = AvailabilityDate::create([
            'tutor_profile_id' => $tutorProfile->id,
            'date' => $this->futureDate,
        ]);

        $slot = $availabilityDate->slots()->create([
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        return [$tutorUser, $tutorProfile, $service, $slot];
    }

    private function createSessionFor(TutorProfile $tutor, Service $service, AvailabilitySlot $slot): TeachingSession
    {
        $student = User::factory()->create();

        $booking = Booking::create([
            'student_id' => $student->id,
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'price' => $service->price,
            'currency' => $service->currency,
            'status' => 'accepted',
        ]);

        $order = app(OrderService::class)->createForBooking($booking);
        app(BookingConfirmationService::class)->confirm($order);
        $booking = $booking->fresh();

        $session = TeachingSession::create([
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'scheduled',
        ]);
        $booking->teachingSessions()->attach($session->id);

        return $session;
    }

    private function createLessonFor(TutorProfile $tutor): Lesson
    {
        $course = $tutor->courses()->create([
            'curriculum_id' => $this->curriculum->id,
            'grade_id' => $this->grade->id,
            'subject_id' => $this->subject->id,
            'title' => 'Algebra Fundamentals',
            'description' => 'Intro to algebra.',
            'estimated_duration_minutes' => 120,
            'difficulty' => 'beginner',
            'language' => 'English',
            'status' => 'draft',
        ]);
        $chapter = $course->chapters()->create(['title' => 'Introduction', 'position' => 0, 'status' => 'published']);

        return $chapter->lessons()->create(['title' => 'What is Algebra?', 'position' => 0, 'status' => 'published']);
    }

    private function createBlockFor(Lesson $lesson): LessonBlock
    {
        return $lesson->blocks()->create([
            'block_type' => 'rich_text',
            'position' => 0,
            'title' => 'Welcome',
            'content' => ['html' => '<p>Hello</p>'],
            'settings' => [],
            'status' => 'published',
        ]);
    }

    public function test_tutor_can_assign_a_lesson_to_a_session(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $session = $this->createSessionFor($tutorProfile, $service, $slot);
        $lesson = $this->createLessonFor($tutorProfile);

        Sanctum::actingAs($tutorUser);

        $response = $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $lesson->id]);

        $response->assertCreated();
        $response->assertJsonPath('session_lesson.lesson.id', $lesson->id);
        $this->assertDatabaseHas('session_lessons', [
            'teaching_session_id' => $session->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_tutor_cannot_assign_another_tutors_lesson(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $session = $this->createSessionFor($tutorProfile, $service, $slot);

        [, $otherTutorProfile] = $this->createTutorWithService();
        $otherLesson = $this->createLessonFor($otherTutorProfile);

        Sanctum::actingAs($tutorUser);

        $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $otherLesson->id])
            ->assertForbidden();
    }

    public function test_tutor_cannot_assign_the_same_lesson_twice(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $session = $this->createSessionFor($tutorProfile, $service, $slot);
        $lesson = $this->createLessonFor($tutorProfile);

        Sanctum::actingAs($tutorUser);

        $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $lesson->id])->assertCreated();
        $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $lesson->id])
            ->assertUnprocessable()->assertJsonValidationErrors('lesson_id');
    }

    public function test_tutor_cannot_assign_a_lesson_whose_subject_grade_or_curriculum_dont_match_the_sessions_service(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $session = $this->createSessionFor($tutorProfile, $service, $slot);

        $otherSubject = Subject::create(['name' => 'Physics', 'is_active' => true]);
        $mismatchedCourse = $tutorProfile->courses()->create([
            'curriculum_id' => $this->curriculum->id,
            'grade_id' => $this->grade->id,
            'subject_id' => $otherSubject->id,
            'title' => 'Physics Fundamentals',
            'description' => 'Newtonian mechanics.',
            'estimated_duration_minutes' => 120,
            'difficulty' => 'beginner',
            'language' => 'English',
            'status' => 'published',
        ]);
        $chapter = $mismatchedCourse->chapters()->create(['title' => 'Introduction', 'position' => 0, 'status' => 'published']);
        $mismatchedLesson = $chapter->lessons()->create(['title' => 'Forces', 'position' => 0, 'status' => 'published']);

        Sanctum::actingAs($tutorUser);

        $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $mismatchedLesson->id])
            ->assertUnprocessable()->assertJsonValidationErrors('lesson_id');
        $this->assertDatabaseMissing('session_lessons', ['lesson_id' => $mismatchedLesson->id]);
    }

    public function test_tutor_cannot_assign_a_draft_lesson_to_a_session(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $session = $this->createSessionFor($tutorProfile, $service, $slot);
        $lesson = $this->createLessonFor($tutorProfile);
        $lesson->update(['status' => 'draft']);

        Sanctum::actingAs($tutorUser);

        $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $lesson->id])
            ->assertUnprocessable()->assertJsonValidationErrors('lesson_id');
        $this->assertDatabaseMissing('session_lessons', ['lesson_id' => $lesson->id]);
    }

    public function test_tutor_can_remove_and_reorder_assigned_lessons(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $session = $this->createSessionFor($tutorProfile, $service, $slot);
        $lessonA = $this->createLessonFor($tutorProfile);
        $lessonB = $this->createLessonFor($tutorProfile);

        Sanctum::actingAs($tutorUser);

        $first = $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $lessonA->id])->json('session_lesson');
        $second = $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $lessonB->id])->json('session_lesson');

        $reorder = $this->patchJson("/api/tutor/sessions/{$session->id}/lessons/reorder", [
            'session_lesson_ids' => [$second['id'], $first['id']],
        ]);
        $reorder->assertOk();
        $reorder->assertJsonPath('session_lessons.0.id', $second['id']);
        $reorder->assertJsonPath('session_lessons.1.id', $first['id']);

        $this->deleteJson("/api/tutor/sessions/{$session->id}/lessons/{$first['id']}")->assertOk();
        $this->assertDatabaseMissing('session_lessons', ['id' => $first['id']]);
        // The underlying Lesson itself must never be touched.
        $this->assertDatabaseHas('lessons', ['id' => $lessonA->id]);
    }

    public function test_manage_lesson_content_lists_all_blocks_and_current_assignments(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $session = $this->createSessionFor($tutorProfile, $service, $slot);
        $lesson = $this->createLessonFor($tutorProfile);
        $blockA = $this->createBlockFor($lesson);
        $blockB = $this->createBlockFor($lesson);

        Sanctum::actingAs($tutorUser);

        $sessionLesson = $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $lesson->id])->json('session_lesson');

        // Nothing is assigned automatically just because the lesson was.
        $index = $this->getJson("/api/tutor/session-lessons/{$sessionLesson['id']}/blocks");
        $index->assertOk();
        $index->assertJsonCount(2, 'blocks');
        $index->assertJsonCount(0, 'assignments');
        $this->assertDatabaseCount('session_lesson_blocks', 0);

        $assign = $this->postJson("/api/tutor/session-lessons/{$sessionLesson['id']}/blocks", [
            'lesson_block_id' => $blockA->id,
            'availability_mode' => 'always_available',
            'completion_mode' => 'not_tracked',
            'attempts_mode' => 'unlimited',
            'visibility' => 'visible',
        ]);
        $assign->assertCreated();
        $assign->assertJsonPath('session_lesson_block.is_available', true);

        $indexAfter = $this->getJson("/api/tutor/session-lessons/{$sessionLesson['id']}/blocks");
        $indexAfter->assertJsonCount(1, 'assignments');

        // blockB was never assigned.
        $this->assertDatabaseMissing('session_lesson_blocks', ['lesson_block_id' => $blockB->id]);
    }

    public function test_tutor_cannot_assign_a_draft_lesson_block_to_a_session(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $session = $this->createSessionFor($tutorProfile, $service, $slot);
        $lesson = $this->createLessonFor($tutorProfile);
        $block = $this->createBlockFor($lesson);
        $block->update(['status' => 'draft']);

        Sanctum::actingAs($tutorUser);
        $sessionLesson = $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $lesson->id])->json('session_lesson');

        $this->postJson("/api/tutor/session-lessons/{$sessionLesson['id']}/blocks", [
            'lesson_block_id' => $block->id,
            'availability_mode' => 'always_available',
            'completion_mode' => 'not_tracked',
            'attempts_mode' => 'unlimited',
            'visibility' => 'visible',
        ])->assertUnprocessable()->assertJsonValidationErrors('lesson_block_id');
        $this->assertDatabaseMissing('session_lesson_blocks', ['lesson_block_id' => $block->id]);
    }

    public function test_scheduled_release_requires_available_from(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $session = $this->createSessionFor($tutorProfile, $service, $slot);
        $lesson = $this->createLessonFor($tutorProfile);
        $block = $this->createBlockFor($lesson);

        Sanctum::actingAs($tutorUser);
        $sessionLesson = $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $lesson->id])->json('session_lesson');

        $response = $this->postJson("/api/tutor/session-lessons/{$sessionLesson['id']}/blocks", [
            'lesson_block_id' => $block->id,
            'availability_mode' => 'scheduled_release',
            'completion_mode' => 'not_tracked',
            'attempts_mode' => 'unlimited',
            'visibility' => 'visible',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('available_from');
    }

    public function test_tutor_can_configure_completion_attempts_and_passing_score(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $session = $this->createSessionFor($tutorProfile, $service, $slot);
        $lesson = $this->createLessonFor($tutorProfile);
        $block = $this->createBlockFor($lesson);

        Sanctum::actingAs($tutorUser);
        $sessionLesson = $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $lesson->id])->json('session_lesson');

        $response = $this->postJson("/api/tutor/session-lessons/{$sessionLesson['id']}/blocks", [
            'lesson_block_id' => $block->id,
            'availability_mode' => 'always_available',
            'completion_mode' => 'required',
            'completion_rule' => 'pass_activity',
            'attempts_mode' => 'limited',
            'max_attempts' => 3,
            'passing_score' => 70,
            'visibility' => 'hidden',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('session_lesson_block.completion_mode', 'required');
        $response->assertJsonPath('session_lesson_block.completion_rule', 'pass_activity');
        $response->assertJsonPath('session_lesson_block.attempts_mode', 'limited');
        $response->assertJsonPath('session_lesson_block.max_attempts', 3);
        $response->assertJsonPath('session_lesson_block.passing_score', '70.00');
        $response->assertJsonPath('session_lesson_block.visibility', 'hidden');
    }

    public function test_completion_rule_is_required_when_completion_is_tracked(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $session = $this->createSessionFor($tutorProfile, $service, $slot);
        $lesson = $this->createLessonFor($tutorProfile);
        $block = $this->createBlockFor($lesson);

        Sanctum::actingAs($tutorUser);
        $sessionLesson = $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $lesson->id])->json('session_lesson');

        $response = $this->postJson("/api/tutor/session-lessons/{$sessionLesson['id']}/blocks", [
            'lesson_block_id' => $block->id,
            'availability_mode' => 'always_available',
            'completion_mode' => 'optional',
            'attempts_mode' => 'unlimited',
            'visibility' => 'visible',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('completion_rule');
    }

    public function test_limited_attempts_requires_max_attempts_on_session_block(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $session = $this->createSessionFor($tutorProfile, $service, $slot);
        $lesson = $this->createLessonFor($tutorProfile);
        $block = $this->createBlockFor($lesson);

        Sanctum::actingAs($tutorUser);
        $sessionLesson = $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $lesson->id])->json('session_lesson');

        $response = $this->postJson("/api/tutor/session-lessons/{$sessionLesson['id']}/blocks", [
            'lesson_block_id' => $block->id,
            'availability_mode' => 'always_available',
            'completion_mode' => 'not_tracked',
            'attempts_mode' => 'limited',
            'visibility' => 'visible',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('max_attempts');
    }

    public function test_a_partial_availability_update_preserves_other_delivery_configuration(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $session = $this->createSessionFor($tutorProfile, $service, $slot);
        $lesson = $this->createLessonFor($tutorProfile);
        $block = $this->createBlockFor($lesson);

        Sanctum::actingAs($tutorUser);
        $sessionLesson = $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $lesson->id])->json('session_lesson');
        $assigned = $this->postJson("/api/tutor/session-lessons/{$sessionLesson['id']}/blocks", [
            'lesson_block_id' => $block->id,
            'availability_mode' => 'manual_release',
            'completion_mode' => 'required',
            'completion_rule' => 'submit_work',
            'attempts_mode' => 'limited',
            'max_attempts' => 2,
            'passing_score' => 60,
            'visibility' => 'visible',
        ])->json('session_lesson_block');

        // Mirrors the tutor UI's quick "release now" toggle, which only sends availability fields.
        $release = $this->patchJson("/api/tutor/session-lessons/{$sessionLesson['id']}/blocks/{$assigned['id']}", [
            'availability_mode' => 'manual_release',
            'is_manually_released' => true,
        ]);

        $release->assertOk();
        $release->assertJsonPath('session_lesson_block.is_available', true);
        $release->assertJsonPath('session_lesson_block.completion_mode', 'required');
        $release->assertJsonPath('session_lesson_block.completion_rule', 'submit_work');
        $release->assertJsonPath('session_lesson_block.attempts_mode', 'limited');
        $release->assertJsonPath('session_lesson_block.max_attempts', 2);
        $release->assertJsonPath('session_lesson_block.passing_score', '60.00');
    }

    public function test_manual_release_toggles_availability(): void
    {
        [$tutorUser, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $session = $this->createSessionFor($tutorProfile, $service, $slot);
        $lesson = $this->createLessonFor($tutorProfile);
        $block = $this->createBlockFor($lesson);

        Sanctum::actingAs($tutorUser);
        $sessionLesson = $this->postJson("/api/tutor/sessions/{$session->id}/lessons", ['lesson_id' => $lesson->id])->json('session_lesson');
        $assigned = $this->postJson("/api/tutor/session-lessons/{$sessionLesson['id']}/blocks", [
            'lesson_block_id' => $block->id,
            'availability_mode' => 'manual_release',
            'completion_mode' => 'not_tracked',
            'attempts_mode' => 'unlimited',
            'visibility' => 'visible',
        ])->json('session_lesson_block');

        $this->assertFalse($assigned['is_available']);

        $release = $this->patchJson("/api/tutor/session-lessons/{$sessionLesson['id']}/blocks/{$assigned['id']}", [
            'availability_mode' => 'manual_release',
            'is_manually_released' => true,
        ]);
        $release->assertOk();
        $release->assertJsonPath('session_lesson_block.is_available', true);
    }

    public function test_tutor_cannot_manage_content_for_another_tutors_session(): void
    {
        [, $tutorProfile, $service, $slot] = $this->createTutorWithService();
        $session = $this->createSessionFor($tutorProfile, $service, $slot);

        [$otherTutorUser] = $this->createTutorWithService();
        Sanctum::actingAs($otherTutorUser);

        $this->getJson("/api/tutor/sessions/{$session->id}/lessons")->assertForbidden();
    }
}
