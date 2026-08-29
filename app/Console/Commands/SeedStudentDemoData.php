<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\ProductType;
use App\Enums\SessionStatus;
use App\Models\AvailabilitySlot;
use App\Models\Booking;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Order;
use App\Models\SelfPacedAssessment;
use App\Models\SelfPacedCourse;
use App\Models\SelfPacedSurveyContent;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TeachingSession;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use App\Services\Booking\SessionSchedulingService;
use App\Services\LearnerProgress\ActivityProgressService;
use App\Services\LearnerProgress\SelfPacedAssessmentAttemptService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One-off: give a student demo data against a specific tutor — a spread of
 * self-paced enrollments at different progress stages, plus a spread of
 * tutor-assisted bookings at different lifecycle stages. Additive, not
 * destructive — never touches the student's or tutor's existing records.
 * Throwaway — remove after running.
 */
class SeedStudentDemoData extends Command
{
    protected $signature = 'demo:seed-student {student-email} {tutor-email} {--also=* : Additional tutor emails to also enroll the student with, one untouched course each, for cross-tutor variety}';

    protected $description = 'Enroll a student in a few self-paced courses (with varied progress) and create a few tutor-assisted bookings.';

    public function handle(
        ActivityProgressService $activityProgress,
        SelfPacedAssessmentAttemptService $assessmentAttempts,
        SessionSchedulingService $sessionScheduling,
    ): int {
        $student = User::where('email', $this->argument('student-email'))->first();
        $tutorUser = User::where('email', $this->argument('tutor-email'))->first();

        if (! $student || ! $tutorUser?->tutorProfile) {
            $this->error('Student or tutor not found.');

            return self::FAILURE;
        }

        $student->forceFill(['email_verified_at' => $student->email_verified_at ?? now()])->save();
        $tutor = $tutorUser->tutorProfile;

        $this->seedEnrollments($student, $tutor, $activityProgress, $assessmentAttempts);

        if (Booking::where('student_id', $student->id)->where('tutor_profile_id', $tutor->id)->exists()) {
            $this->line('Bookings with this tutor already seeded — skipping (re-running would duplicate them).');
        } else {
            $this->seedBookings($student, $tutor, $sessionScheduling);
        }

        $this->seedExtraTutorEnrollments($student, $this->option('also'), $sessionScheduling);

        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * Gives the student one untouched enrollment with each additional
     * tutor's first self-paced course, plus a spread of tutor-assisted
     * bookings — for cross-tutor variety on platforms where only the
     * primary demo tutor has a rich-enough catalogue on its own. Skips a
     * tutor with no self-paced courses at all rather than failing the
     * whole command; a tutor with fewer than 3 services gets a minimal
     * bookable catalogue built for it first (see ensureBookableCatalogue).
     */
    private function seedExtraTutorEnrollments(User $student, array $tutorEmails, SessionSchedulingService $sessionScheduling): void
    {
        foreach ($tutorEmails as $email) {
            $tutorUser = User::where('email', $email)->first();
            $tutor = $tutorUser?->tutorProfile;

            if (! $tutor) {
                $this->warn("No tutor found for \"{$email}\" — skipping.");

                continue;
            }

            $course = $tutor->selfPacedCourses()->orderBy('id')->first();

            if ($course) {
                $this->enroll($student, $course);
                $this->line("Enrolled in \"{$course->title}\" (tutor: {$email}) — not started.");
            } else {
                $this->warn("Tutor \"{$email}\" has no self-paced courses — skipping enrollment.");
            }

            $this->ensureBookableCatalogue($tutor);

            if (Booking::where('student_id', $student->id)->where('tutor_profile_id', $tutor->id)->exists()) {
                $this->line("Bookings with \"{$email}\" already seeded — skipping (re-running would duplicate them).");
            } else {
                $this->seedBookings($student, $tutor, $sessionScheduling);
            }
        }
    }

    /**
     * Gives a tutor a minimal bookable catalogue (one approved subject,
     * three published services, two weeks of availability) if they don't
     * already have enough of one for seedBookings() to work — a smaller
     * version of what ResetTutorDemoData builds for the primary demo tutor,
     * just enough to support three realistic bookings.
     */
    private function ensureBookableCatalogue(TutorProfile $tutor): void
    {
        if ($tutor->services()->count() >= 3) {
            return;
        }

        $subject = Subject::firstOrCreate(['name' => 'Mathematics'], ['is_active' => true]);
        $grade = Grade::firstOrCreate(['level' => 10], ['name' => 'Grade 10', 'is_active' => true]);
        $category = ServiceCategory::firstOrCreate(['name' => 'Private Lesson']);
        $format = SessionFormat::firstOrCreate(['name' => 'Online']);

        if (! $tutor->tutorSubjects()->where('subject_id', $subject->id)->exists()) {
            $tutorSubject = TutorSubject::create([
                'tutor_profile_id' => $tutor->id,
                'subject_id' => $subject->id,
                'status' => 'approved',
            ]);
            $tutorSubject->tutorSubjectGrades()->create(['grade_id' => $grade->id]);
        }

        $existingTitles = $tutor->services()->pluck('title')->all();
        $serviceDefs = [
            ['title' => 'Grade 10 Mathematics Tutoring', 'price' => 350, 'sessions' => 8],
            ['title' => 'Grade 10 Mathematics Exam Prep', 'price' => 400, 'sessions' => 6],
            ['title' => 'Grade 10 Mathematics Homework Help', 'price' => 250, 'sessions' => 4],
        ];
        foreach ($serviceDefs as $def) {
            if (in_array($def['title'], $existingTitles, true)) {
                continue;
            }
            $tutor->services()->create([
                'subject_id' => $subject->id,
                'grade_id' => $grade->id,
                'service_category_id' => $category->id,
                'session_format_id' => $format->id,
                'title' => $def['title'],
                'description' => 'One-on-one Grade 10 Mathematics tutoring, focused on building real exam-ready confidence.',
                'price' => $def['price'],
                'currency' => 'ZAR',
                'session_duration_minutes' => 60,
                'sessions_included' => $def['sessions'],
                'validity_period_days' => 60,
                'max_students_per_session' => 1,
                'visibility' => 'published',
            ]);
        }
        $this->line("Gave tutor \"{$tutor->user->email}\" a minimal bookable catalogue (subject + services).");

        if ($tutor->availabilityDates()->where('date', '>=', Carbon::today()->toDateString())->count() < 7) {
            $start = Carbon::today();
            for ($date = $start->copy(); $date->lt($start->copy()->addDays(14)); $date->addDay()) {
                $availabilityDate = $tutor->availabilityDates()->firstOrCreate(['date' => $date->toDateString()]);
                $availabilityDate->slots()->firstOrCreate(['start_time' => '09:00', 'end_time' => '12:00']);
                $availabilityDate->slots()->firstOrCreate(['start_time' => '14:00', 'end_time' => '17:00']);
            }
            $this->line("Gave tutor \"{$tutor->user->email}\" 14 days of availability.");
        }
    }

    /**
     * Enrolls the student in three of the tutor's courses at three
     * different progress stages: untouched, partially through, and fully
     * completed (earning a certificate) — a realistic spread for demoing
     * both the student dashboard and the tutor's Learning Analytics.
     */
    private function seedEnrollments(
        User $student,
        TutorProfile $tutor,
        ActivityProgressService $activityProgress,
        SelfPacedAssessmentAttemptService $assessmentAttempts,
    ): void {
        $courses = $tutor->selfPacedCourses()->orderBy('id')->with('modules.activities', 'modules.assessments.attempts')->get();

        if ($courses->count() < 3) {
            $this->warn('Tutor has fewer than 3 self-paced courses — skipping enrollments.');

            return;
        }

        [$untouched, $inProgress, $completed] = $courses->take(3);

        $this->enroll($student, $untouched);
        $this->line("Enrolled in \"{$untouched->title}\" — not started.");

        $inProgressEnrollment = $this->enroll($student, $inProgress);
        $firstModule = $inProgress->modules->sortBy('position')->first();
        foreach ($firstModule?->activities ?? [] as $activity) {
            $activityProgress->markComplete($inProgressEnrollment, $activity);
        }
        $this->line("Enrolled in \"{$inProgress->title}\" — first chapter's activities completed.");

        $completedEnrollment = $this->enroll($student, $completed);
        foreach ($completed->modules->sortBy('position') as $module) {
            foreach ($module->activities as $activity) {
                $activityProgress->markComplete($completedEnrollment, $activity);
            }
            foreach ($module->assessments as $assessment) {
                $this->passAssessment($completedEnrollment, $assessment, $assessmentAttempts);
            }
        }
        $this->line("Enrolled in \"{$completed->title}\" — fully completed, certificate issued.");
    }

    private function enroll(User $student, SelfPacedCourse $course): Enrollment
    {
        $existing = Enrollment::where('student_id', $student->id)->where('self_paced_course_id', $course->id)->first();

        if ($existing) {
            return $existing;
        }

        $order = Order::create([
            'student_id' => $student->id,
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'status' => OrderStatus::Paid,
            'currency' => $course->currency ?? 'ZAR',
            'total_amount' => $course->price,
            'discount_amount' => 0,
            'final_amount' => $course->price,
        ]);
        $order->items()->create([
            'product_type' => ProductType::CourseOffering->value,
            'product_id' => $course->id,
            'quantity' => 1,
            'unit_price' => $course->price,
            'discount' => 0,
            'total' => $course->price,
        ]);
        $order->payments()->create([
            'provider' => 'payfast',
            'payment_reference' => 'DEMO-'.strtoupper(Str::random(10)),
            'amount' => $course->price,
            'currency' => $course->currency ?? 'ZAR',
            'status' => PaymentTransactionStatus::Successful,
            'paid_at' => now(),
        ]);

        return Enrollment::create([
            'student_id' => $student->id,
            'self_paced_course_id' => $course->id,
            'order_id' => $order->id,
            'status' => 'active',
            'enrolled_at' => now(),
            'course_version' => $course->course_version,
        ]);
    }

    /**
     * Passes a SurveyJS-provider assessment by answering every question
     * with its own configured correct answer — provider-agnostic enough for
     * this demo's SurveyJS-only assessments; skips anything else (e.g. an
     * unconfigured or H5P assessment) rather than guessing at content.
     */
    private function passAssessment(Enrollment $enrollment, SelfPacedAssessment $assessment, SelfPacedAssessmentAttemptService $assessmentAttempts): void
    {
        if ($assessment->provider?->value !== 'surveyjs') {
            return;
        }

        $surveyContentId = $assessment->provider_config['survey_content_id'] ?? null;
        $surveyContent = $surveyContentId ? SelfPacedSurveyContent::with('questions')->find($surveyContentId) : null;

        if (! $surveyContent) {
            return;
        }

        $answers = [];
        foreach ($surveyContent->questions as $question) {
            $correct = $question->definition['correctAnswer'] ?? null;
            if ($correct !== null) {
                $answers["question_{$question->id}"] = $correct;
            }
        }

        $attempt = $assessmentAttempts->start($assessment, $enrollment);
        $assessmentAttempts->complete($attempt, $answers, $enrollment);
    }

    /**
     * Creates three tutor-assisted bookings at three different lifecycle
     * stages: a fresh request awaiting the tutor's response, a confirmed
     * booking with a real upcoming scheduled session, and a confirmed
     * booking with a completed past session.
     */
    private function seedBookings(User $student, TutorProfile $tutor, SessionSchedulingService $sessionScheduling): void
    {
        $services = $tutor->services()->orderBy('id')->get();

        if ($services->count() < 3) {
            $this->warn('Tutor has fewer than 3 services — skipping bookings.');

            return;
        }

        [$serviceA, $serviceB, $serviceC] = $services->take(3);

        // 1. Pending — a fresh request, not yet accepted, against a real
        // open slot (bookings.availability_slot_id is required — it's
        // always the slot the student actually picked in the marketplace).
        $slotA = $this->firstOpenSlot($tutor, Carbon::today()->addDays(5));
        if ($slotA) {
            $this->booking($student, $tutor, $serviceA, BookingStatus::Pending, $slotA);
            $this->line("Booked \"{$serviceA->title}\" — pending the tutor's response.");
        } else {
            $this->warn("No open availability found for \"{$serviceA->title}\" — skipped.");
        }

        // 2. Confirmed, with a real upcoming session scheduled through the
        // actual scheduling service (so availability/overlap rules apply).
        $slotB = $this->firstOpenSlot($tutor, Carbon::today()->addDays(3));
        if ($slotB) {
            $confirmedUpcoming = $this->booking($student, $tutor, $serviceB, BookingStatus::Confirmed, $slotB, withOrder: true);
            $sessionScheduling->scheduleSession($confirmedUpcoming, [
                'date' => $slotB->availabilityDate->date->toDateString(),
                'start_time' => $slotB->start_time,
                'end_time' => Carbon::parse($slotB->start_time)->addHour()->format('H:i'),
            ]);
            $this->line("Booked \"{$serviceB->title}\" — confirmed, with an upcoming session scheduled.");
        } else {
            $this->warn("No open availability found for \"{$serviceB->title}\" — skipped.");
        }

        // 3. Confirmed, with a completed past session — history, not a new
        // scheduling action. Past availability has since elapsed, so a slot
        // for that date is (re)created here the same way it would once have
        // existed when it was still current, then the session itself is
        // created directly (SessionSchedulingService only understands
        // future/open slots — completing history isn't "scheduling").
        $pastDate = Carbon::today()->subDays(4);
        $slotC = $tutor->availabilityDates()->firstOrCreate(['date' => $pastDate->toDateString()])
            ->slots()->firstOrCreate(['start_time' => '10:00', 'end_time' => '11:00']);
        $confirmedPast = $this->booking($student, $tutor, $serviceC, BookingStatus::Confirmed, $slotC, withOrder: true);
        $pastSession = TeachingSession::create([
            'tutor_profile_id' => $tutor->id,
            'service_id' => $serviceC->id,
            'date' => $pastDate->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => SessionStatus::Completed,
            'completed_at' => $pastDate->copy()->setTime(11, 0),
            'tutor_notes' => 'Great progress this session — ready for the next topic.',
        ]);
        $confirmedPast->teachingSessions()->attach($pastSession->id);
        $this->line("Booked \"{$serviceC->title}\" — confirmed, with a completed past session.");
    }

    private function booking(User $student, TutorProfile $tutor, Service $service, BookingStatus $status, AvailabilitySlot $slot, bool $withOrder = false): Booking
    {
        $order = null;

        if ($withOrder) {
            $order = Order::create([
                'student_id' => $student->id,
                'order_number' => 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
                'status' => OrderStatus::Paid,
                'currency' => $service->currency ?? 'ZAR',
                'total_amount' => $service->price,
                'discount_amount' => 0,
                'final_amount' => $service->price,
            ]);
        }

        $booking = Booking::create([
            'student_id' => $student->id,
            'tutor_profile_id' => $tutor->id,
            'service_id' => $service->id,
            'availability_slot_id' => $slot->id,
            'order_id' => $order?->id,
            'date' => $slot->availabilityDate->date->toDateString(),
            'start_time' => $slot->start_time,
            'end_time' => Carbon::parse($slot->start_time)->addHour()->format('H:i'),
            'price' => $service->price,
            'currency' => $service->currency,
            'status' => $status,
            'message' => 'Looking forward to the session!',
        ]);

        if ($order) {
            $order->items()->create([
                'product_type' => ProductType::TutoringServiceBooking->value,
                'product_id' => $booking->id,
                'quantity' => 1,
                'unit_price' => $service->price,
                'discount' => 0,
                'total' => $service->price,
            ]);
            $order->payments()->create([
                'provider' => 'payfast',
                'payment_reference' => 'DEMO-'.strtoupper(Str::random(10)),
                'amount' => $service->price,
                'currency' => $service->currency ?? 'ZAR',
                'status' => PaymentTransactionStatus::Successful,
                'paid_at' => now(),
            ]);
        }

        return $booking;
    }

    /**
     * The tutor's next open availability slot on or after the given date.
     */
    private function firstOpenSlot(TutorProfile $tutor, Carbon $onOrAfter): ?AvailabilitySlot
    {
        $availabilityDate = $tutor->availabilityDates()
            ->where('date', '>=', $onOrAfter->toDateString())
            ->orderBy('date')
            ->with('slots')
            ->first();

        return $availabilityDate?->slots->first();
    }
}
