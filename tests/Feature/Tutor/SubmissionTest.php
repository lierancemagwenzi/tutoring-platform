<?php

namespace Tests\Feature\Tutor;

use App\Models\AvailabilityDate;
use App\Models\Booking;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\SessionLesson;
use App\Models\SessionLessonBlock;
use App\Models\Subject;
use App\Models\Submission;
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
     * @return array{0: User, 1: User, 2: SessionLessonBlock}
     */
    private function createDeliveryWithSubmission(?float $maxScore = 100, ?float $passingScore = 50): array
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

        $studentUser = User::factory()->create();

        $booking = Booking::create([
            'student_id' => $studentUser->id,
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

        // Auto-scheduled by BookingConfirmationService from the booking's
        // own requested date/time.
        $session = $booking->teachingSessions()->firstOrFail();

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
            'type' => 'assignment',
            'title' => 'Essay 1',
            'status' => 'draft',
            'submission_type' => 'text',
            'max_score' => $maxScore,
        ]);

        $block = $lesson->blocks()->create([
            'block_type' => 'assignment',
            'position' => 0,
            'title' => 'Essay 1',
            'content' => ['learning_activity_id' => $activity->id],
            'settings' => [],
            'status' => 'published',
        ]);

        $sessionLesson = SessionLesson::create([
            'teaching_session_id' => $session->id,
            'lesson_id' => $lesson->id,
            'position' => 0,
        ]);

        $sessionLessonBlock = SessionLessonBlock::create([
            'session_lesson_id' => $sessionLesson->id,
            'lesson_block_id' => $block->id,
            'availability_mode' => 'always_available',
            'attempts_mode' => 'unlimited',
            'completion_mode' => 'not_tracked',
            'visibility' => 'visible',
            'passing_score' => $passingScore,
        ]);

        $submission = Submission::create([
            'session_lesson_block_id' => $sessionLessonBlock->id,
            'student_id' => $studentUser->id,
            'attempt_number' => 1,
            'status' => 'submitted',
            'submission_text' => ['html' => '<p>My essay.</p>', 'json' => null],
            'submitted_at' => now(),
        ]);

        return [$tutorUser, $studentUser, $sessionLessonBlock, $submission];
    }

    public function test_tutor_can_list_submissions_for_a_delivery_instance(): void
    {
        [$tutor, , $slb, $submission] = $this->createDeliveryWithSubmission();
        Sanctum::actingAs($tutor);

        $response = $this->getJson("/api/tutor/session-lesson-blocks/{$slb->id}/submissions");

        $response->assertOk();
        $response->assertJsonCount(1, 'submissions');
        $response->assertJsonPath('submissions.0.id', $submission->id);
        $response->assertJsonPath('submissions.0.student.id', $submission->student_id);
    }

    public function test_tutor_can_mark_a_submission_under_review(): void
    {
        [$tutor, , , $submission] = $this->createDeliveryWithSubmission();
        Sanctum::actingAs($tutor);

        $response = $this->patchJson("/api/tutor/submissions/{$submission->id}/review");

        $response->assertOk();
        $response->assertJsonPath('submission.status', 'under_review');
    }

    public function test_tutor_can_return_a_submission_for_revision(): void
    {
        [$tutor, , , $submission] = $this->createDeliveryWithSubmission();
        Sanctum::actingAs($tutor);

        $response = $this->patchJson("/api/tutor/submissions/{$submission->id}/return");

        $response->assertOk();
        $response->assertJsonPath('submission.status', 'returned');
    }

    public function test_tutor_can_grade_a_submission_and_pass_fail_is_derived_from_passing_score(): void
    {
        [$tutor, , , $submission] = $this->createDeliveryWithSubmission(maxScore: 100, passingScore: 60);
        Sanctum::actingAs($tutor);

        $passing = $this->patchJson("/api/tutor/submissions/{$submission->id}/grade", [
            'score' => 75,
            'feedback_text_html' => '<p>Great work.</p>',
        ]);
        $passing->assertOk();
        $passing->assertJsonPath('submission.status', 'graded');
        $passing->assertJsonPath('submission.score', '75.00');
        $passing->assertJsonPath('submission.passed', true);
        $passing->assertJsonPath('submission.percentage', 75);
        $passing->assertJsonPath('submission.feedback_text.html', '<p>Great work.</p>');

        [$otherTutor, , , $failingSubmission] = $this->createDeliveryWithSubmission(maxScore: 100, passingScore: 60);
        Sanctum::actingAs($otherTutor);
        $failing = $this->patchJson("/api/tutor/submissions/{$failingSubmission->id}/grade", ['score' => 40]);
        $failing->assertOk();
        $failing->assertJsonPath('submission.passed', false);
    }

    public function test_grade_cannot_exceed_the_activitys_maximum_score(): void
    {
        [$tutor, , , $submission] = $this->createDeliveryWithSubmission(maxScore: 100);
        Sanctum::actingAs($tutor);

        $response = $this->patchJson("/api/tutor/submissions/{$submission->id}/grade", ['score' => 150]);

        $response->assertUnprocessable()->assertJsonValidationErrors('score');
    }

    public function test_tutor_can_publish_a_graded_submission(): void
    {
        [$tutor, , , $submission] = $this->createDeliveryWithSubmission();
        Sanctum::actingAs($tutor);

        $this->patchJson("/api/tutor/submissions/{$submission->id}/grade", ['score' => 80]);

        $response = $this->patchJson("/api/tutor/submissions/{$submission->id}/publish");
        $response->assertOk();
        $this->assertNotNull($response->json('submission.published_at'));
    }

    public function test_cannot_publish_a_submission_that_has_not_been_graded(): void
    {
        [$tutor, , , $submission] = $this->createDeliveryWithSubmission();
        Sanctum::actingAs($tutor);

        $this->patchJson("/api/tutor/submissions/{$submission->id}/publish")
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_tutor_can_attach_and_remove_feedback_files(): void
    {
        [$tutor, , , $submission] = $this->createDeliveryWithSubmission();
        Sanctum::actingAs($tutor);

        $file = UploadedFile::fake()->create('annotated.pdf', 100, 'application/pdf');
        $attach = $this->postJson("/api/tutor/submissions/{$submission->id}/feedback-attachments", [
            'media_type' => 'pdf',
            'title' => 'Annotated essay',
            'file' => $file,
        ]);
        $attach->assertCreated();
        $attachmentId = $attach->json('attachment.id');

        $this->deleteJson("/api/tutor/feedback-attachments/{$attachmentId}")->assertOk();
        $this->assertDatabaseMissing('submission_attachments', ['id' => $attachmentId]);
    }

    public function test_tutor_cannot_manage_another_tutors_submissions(): void
    {
        [, , $slb, $submission] = $this->createDeliveryWithSubmission();
        $otherTutor = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $otherTutor->id, 'display_name' => 'Other Tutor']);
        Sanctum::actingAs($otherTutor);

        $this->getJson("/api/tutor/session-lesson-blocks/{$slb->id}/submissions")->assertForbidden();
        $this->patchJson("/api/tutor/submissions/{$submission->id}/review")->assertForbidden();
        $this->patchJson("/api/tutor/submissions/{$submission->id}/grade", ['score' => 90])->assertForbidden();
    }
}
