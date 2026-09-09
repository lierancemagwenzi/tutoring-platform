<?php

namespace Tests\Feature\Student;

use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\SessionLesson;
use App\Models\SessionLessonBlock;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\TeachingSession;
use App\Models\TutorProfile;
use App\Models\User;
use App\Services\Booking\BookingConfirmationService;
use App\Services\Commerce\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private Curriculum $curriculum;

    private Grade $grade;

    private ServiceCategory $category;

    private SessionFormat $onlineFormat;

    private string $futureDate;

    private User $studentUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $this->category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $this->onlineFormat = SessionFormat::create(['name' => 'Online', 'is_active' => true]);
        $this->futureDate = Carbon::now()->addDays(10)->format('Y-m-d');
    }

    /**
     * Creates a tutor + service + booked session, an "assignment" block
     * assigned to that session with the given delivery configuration, and a
     * student who booked it. Returns [studentUser, booking, sessionLessonBlock].
     *
     * @return array{0: User, 1: Booking, 2: SessionLessonBlock}
     */
    private function createAssignmentDelivery(array $sessionLessonBlockOverrides = [], string $blockType = 'assignment'): array
    {
        $tutorUser = User::factory()->tutor()->create();
        $tutorProfile = TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        $service = $tutorProfile->services()->create([
            'subject_id' => $this->subject->id,
            'grade_id' => $this->grade->id,
            'service_category_id' => $this->category->id,
            'session_format_id' => $this->onlineFormat->id,
            'title' => 'Grade 10 Maths',
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

        $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $tutorProfile->id, 'date' => $this->futureDate]);
        $slot = $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '12:00']);

        $this->studentUser = User::factory()->create();

        $booking = Booking::create([
            'student_id' => $this->studentUser->id,
            'tutor_profile_id' => $tutorProfile->id,
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
            'tutor_profile_id' => $tutorProfile->id,
            'service_id' => $service->id,
            'date' => $this->futureDate,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'scheduled',
        ]);
        $booking->teachingSessions()->attach($session->id);

        $course = $tutorProfile->courses()->create([
            'curriculum_id' => $this->curriculum->id,
            'grade_id' => $this->grade->id,
            'subject_id' => $this->subject->id,
            'title' => 'Algebra Fundamentals',
            'description' => 'Intro to algebra.',
            'estimated_duration_minutes' => 120,
            'difficulty' => 'beginner',
            'language' => 'English',
            'status' => 'published',
        ]);
        $chapter = $course->chapters()->create(['title' => 'Introduction', 'position' => 0, 'status' => 'published']);
        $lesson = $chapter->lessons()->create(['title' => 'What is Algebra?', 'position' => 0, 'status' => 'published']);

        $activity = $lesson->learningActivities()->create([
            'type' => $blockType === 'quiz' ? 'assignment' : $blockType,
            'title' => 'Essay 1',
            'status' => 'draft',
            'submission_type' => 'text',
            'max_score' => 100,
        ]);

        $block = $lesson->blocks()->create([
            'block_type' => $blockType,
            'position' => 0,
            'title' => 'Essay 1',
            'content' => $blockType === 'quiz' ? [] : ['learning_activity_id' => $activity->id],
            'settings' => [],
            'status' => 'published',
        ]);

        $sessionLesson = SessionLesson::create([
            'teaching_session_id' => $session->id,
            'lesson_id' => $lesson->id,
            'position' => 0,
        ]);

        $sessionLessonBlock = SessionLessonBlock::create(array_merge([
            'session_lesson_id' => $sessionLesson->id,
            'lesson_block_id' => $block->id,
            'availability_mode' => 'always_available',
            'attempts_mode' => 'unlimited',
            'completion_mode' => 'not_tracked',
            'visibility' => 'visible',
            'passing_score' => 50,
        ], $sessionLessonBlockOverrides));

        return [$this->studentUser, $booking, $sessionLessonBlock];
    }

    public function test_student_can_start_and_resume_a_draft_submission(): void
    {
        [$student, $booking, $slb] = $this->createAssignmentDelivery();
        Sanctum::actingAs($student);

        $first = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions");
        $first->assertCreated();
        $first->assertJsonPath('submission.status', 'draft');
        $first->assertJsonPath('submission.attempt_number', 1);

        // Calling store again while still a draft resumes the same attempt rather than creating a new one.
        $second = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions");
        $second->assertCreated();
        $second->assertJsonPath('submission.id', $first->json('submission.id'));
        $this->assertDatabaseCount('submissions', 1);
    }

    public function test_student_can_update_and_submit_a_draft(): void
    {
        [$student, $booking, $slb] = $this->createAssignmentDelivery();
        Sanctum::actingAs($student);

        $submissionId = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions")->json('submission.id');

        $update = $this->patchJson("/api/submissions/{$submissionId}", [
            'submission_text_html' => '<p>My essay.</p>',
        ]);
        $update->assertOk();
        $update->assertJsonPath('submission.submission_text.html', '<p>My essay.</p>');

        $submit = $this->postJson("/api/submissions/{$submissionId}/submit");
        $submit->assertOk();
        $submit->assertJsonPath('submission.status', 'submitted');
        $this->assertNotNull($submit->json('submission.submitted_at'));
    }

    public function test_submitting_without_required_text_is_rejected(): void
    {
        [$student, $booking, $slb] = $this->createAssignmentDelivery();
        Sanctum::actingAs($student);

        $submissionId = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions")->json('submission.id');

        $submit = $this->postJson("/api/submissions/{$submissionId}/submit");
        $submit->assertUnprocessable()->assertJsonValidationErrors('submission_text_html');
    }

    public function test_student_cannot_edit_or_resubmit_after_submitting(): void
    {
        [$student, $booking, $slb] = $this->createAssignmentDelivery();
        Sanctum::actingAs($student);

        $submissionId = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions")->json('submission.id');
        $this->patchJson("/api/submissions/{$submissionId}", ['submission_text_html' => '<p>Done.</p>']);
        $this->postJson("/api/submissions/{$submissionId}/submit")->assertOk();

        $this->patchJson("/api/submissions/{$submissionId}", ['submission_text_html' => '<p>Edited.</p>'])->assertForbidden();
        $this->postJson("/api/submissions/{$submissionId}/submit")
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_student_cannot_start_a_submission_for_an_ineligible_block_type(): void
    {
        [$student, $booking, $slb] = $this->createAssignmentDelivery(blockType: 'quiz');
        Sanctum::actingAs($student);

        $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions")
            ->assertUnprocessable()->assertJsonValidationErrors('lesson_block_id');
    }

    public function test_student_cannot_start_a_submission_when_the_block_is_unavailable(): void
    {
        [$student, $booking, $slb] = $this->createAssignmentDelivery(['availability_mode' => 'manual_release', 'is_manually_released' => false]);
        Sanctum::actingAs($student);

        $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions")
            ->assertUnprocessable()->assertJsonValidationErrors('lesson_block_id');
    }

    public function test_student_cannot_exceed_the_maximum_number_of_attempts(): void
    {
        [$student, $booking, $slb] = $this->createAssignmentDelivery(['attempts_mode' => 'limited', 'max_attempts' => 1]);
        Sanctum::actingAs($student);

        $first = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions")->json('submission.id');
        $this->patchJson("/api/submissions/{$first}", ['submission_text_html' => '<p>One.</p>']);
        $this->postJson("/api/submissions/{$first}/submit")->assertOk();

        $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions")
            ->assertUnprocessable()->assertJsonValidationErrors('lesson_block_id');
    }

    public function test_student_cannot_start_a_new_attempt_once_graded(): void
    {
        [$student, $booking, $slb] = $this->createAssignmentDelivery(['attempts_mode' => 'unlimited']);
        Sanctum::actingAs($student);

        $submissionId = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions")->json('submission.id');
        $this->patchJson("/api/submissions/{$submissionId}", ['submission_text_html' => '<p>Done.</p>']);
        $this->postJson("/api/submissions/{$submissionId}/submit");

        Submission::find($submissionId)->update(['status' => 'graded', 'score' => 80, 'reviewed_at' => now()]);

        $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions")
            ->assertUnprocessable()->assertJsonValidationErrors('lesson_block_id');
    }

    public function test_student_can_add_and_remove_attachments_on_a_draft(): void
    {
        [$student, $booking, $slb] = $this->createAssignmentDelivery();
        Sanctum::actingAs($student);

        $submissionId = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions")->json('submission.id');

        $file = UploadedFile::fake()->create('essay.pdf', 100, 'application/pdf');
        $attach = $this->postJson("/api/submissions/{$submissionId}/attachments", [
            'media_type' => 'pdf',
            'title' => 'My essay',
            'file' => $file,
        ]);
        $attach->assertCreated();
        $attachmentId = $attach->json('attachment.id');

        $this->deleteJson("/api/submission-attachments/{$attachmentId}")->assertOk();
        $this->assertDatabaseMissing('submission_attachments', ['id' => $attachmentId]);
    }

    public function test_student_cannot_view_or_act_on_another_students_submission(): void
    {
        [$student, $booking, $slb] = $this->createAssignmentDelivery();
        Sanctum::actingAs($student);
        $submissionId = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions")->json('submission.id');

        $otherStudent = User::factory()->create();
        Sanctum::actingAs($otherStudent);

        $this->patchJson("/api/submissions/{$submissionId}", ['submission_text_html' => '<p>Hijacked.</p>'])->assertForbidden();
        $this->postJson("/api/submissions/{$submissionId}/submit")->assertForbidden();
    }

    public function test_student_does_not_see_score_or_feedback_until_published(): void
    {
        [$student, $booking, $slb] = $this->createAssignmentDelivery();
        Sanctum::actingAs($student);

        $submissionId = $this->postJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions")->json('submission.id');
        $this->patchJson("/api/submissions/{$submissionId}", ['submission_text_html' => '<p>Done.</p>']);
        $this->postJson("/api/submissions/{$submissionId}/submit");

        $submission = Submission::find($submissionId);
        $submission->update(['status' => 'graded', 'score' => 90, 'passed' => true, 'reviewed_at' => now()]);

        $index = $this->getJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions");
        $index->assertOk();
        $index->assertJsonPath('submissions.0.is_published', false);
        $index->assertJsonPath('submissions.0.score', null);
        $index->assertJsonPath('submissions.0.passed', null);

        $submission->update(['published_at' => now()]);

        $indexAfter = $this->getJson("/api/bookings/{$booking->id}/lesson-blocks/{$slb->lessonBlock->id}/submissions");
        $indexAfter->assertJsonPath('submissions.0.is_published', true);
        $indexAfter->assertJsonPath('submissions.0.score', '90.00');
        $indexAfter->assertJsonPath('submissions.0.passed', true);
    }
}
