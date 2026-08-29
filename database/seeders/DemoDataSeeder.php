<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\ConnectedAccountProvider;
use App\Enums\MeetingStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AssessmentType;
use App\Models\AvailabilityDate;
use App\Models\AvailabilitySlot;
use App\Models\Booking;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\FinancialTransaction;
use App\Models\Grade;
use App\Models\LearningResource;
use App\Models\Lesson;
use App\Models\LessonBlock;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\SessionLesson;
use App\Models\SessionMeeting;
use App\Models\Subject;
use App\Models\TeachingSession;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use App\Services\Admin\PayoutService;
use App\Services\Admin\TutorApprovalService;
use App\Services\Admin\TutorSubjectApprovalService;
use App\Services\Booking\BookingAcceptanceService;
use App\Services\Booking\BookingChatService;
use App\Services\Booking\BookingConfirmationService;
use App\Services\Commerce\CommissionSnapshotService;
use App\Services\Commerce\EnrollmentService;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\PaymentService;
use App\Services\Commerce\PaymentTicketService;
use App\Services\LearnerProgress\ActivityProgressService;
use App\Services\LearnerProgress\SelfPacedAssessmentAttemptService;
use App\Services\Support\SupportTicketService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Wipes-and-reseeds demo content for a live product demo. Anchored on the
 * user's real tutor account (kept, given full content) plus a wide spread
 * of generated tutors/students/courses/bookings so every part of the app
 * has something to show. Run explicitly (not part of DatabaseSeeder):
 *
 *   php artisan migrate:fresh
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Wherever a real domain service exists (order/payment/commission,
 * booking accept/confirm, tutor/subject approval, payouts, tickets) it is
 * called directly instead of hand-inserting rows, so every side effect
 * (AdminActivityLog rows, in-app notifications, commission splits) fires
 * exactly like it would in production.
 */
class DemoDataSeeder extends Seeder
{
    private const PASSWORD = 'password';

    private Collection $subjects;

    private Collection $grades;

    private Collection $categories;

    private Collection $formats;

    private Collection $resources;

    private Collection $assessmentTypes;

    private Collection $curricula;

    /** @var array<int, FinancialTransaction> Eligible-for-payout transactions collected as we go. */
    private array $payableTransactions = [];

    public function run(
        OrderService $orders,
        PaymentService $payments,
        CommissionSnapshotService $commission,
        EnrollmentService $enrollments,
        BookingAcceptanceService $acceptance,
        BookingConfirmationService $confirmation,
        BookingChatService $chat,
        PayoutService $payouts,
        PaymentTicketService $paymentTickets,
        SupportTicketService $supportTickets,
        TutorApprovalService $tutorApproval,
        TutorSubjectApprovalService $subjectApproval,
        ActivityProgressService $activityProgress,
        SelfPacedAssessmentAttemptService $assessmentAttempts,
    ): void {
        $this->call([
            GradeSeeder::class,
            SubjectSeeder::class,
            ServiceCategorySeeder::class,
            SessionFormatSeeder::class,
            LearningResourceSeeder::class,
            AssessmentTypeSeeder::class,
            CurriculumSeeder::class,
            FaqSeeder::class,
        ]);

        $this->subjects = Subject::where('is_active', true)->get();
        $this->grades = Grade::where('is_active', true)->get();
        $this->categories = ServiceCategory::where('is_active', true)->get();
        $this->formats = SessionFormat::where('is_active', true)->get();
        $this->resources = LearningResource::where('is_active', true)->get();
        $this->assessmentTypes = AssessmentType::where('is_active', true)->get();
        $this->curricula = Curriculum::where('is_active', true)->get();

        $superAdmin = $this->seedAdmins();

        $pinned = $this->seedPinnedTutor();
        $others = $this->seedOtherTutors(24, $tutorApproval, $subjectApproval, $superAdmin);
        $approvedOthers = collect($others)->filter(fn ($t) => $t['user']->status === UserStatus::Approved)->values();

        $pinnedStudent = $this->seedPinnedStudent();
        $otherStudents = User::factory()->count(19)->create(['status' => UserStatus::Approved]);

        $pinnedCourses = $this->seedPinnedTutorCourses($pinned['profile']);
        $otherCourses = collect();
        foreach ($approvedOthers->take(7) as $tutor) {
            $otherCourses->push($this->seedSimpleCourse($tutor['profile']));
        }
        $allCourses = $pinnedCourses->merge($otherCourses);

        $lessons = $this->seedCourseContent($pinned['profile']);

        $this->enrollPinnedStudent($pinnedStudent, $pinnedCourses, $otherCourses, $orders, $payments, $commission, $enrollments, $activityProgress, $assessmentAttempts);
        $this->enrollOtherStudents($otherStudents, $allCourses, $orders, $payments, $commission, $enrollments);

        $pinnedBookingRefs = $this->seedPinnedStudentBookings($pinnedStudent, $pinned, $approvedOthers, $acceptance, $payments, $commission, $confirmation, $chat, $lessons);
        $this->seedOtherBookings($otherStudents, collect([$pinned])->merge($approvedOthers), $acceptance, $payments, $commission, $confirmation, $chat);

        $this->seedPaymentTickets($paymentTickets, $superAdmin, collect([$pinned])->merge($approvedOthers));
        $this->seedSupportTickets($supportTickets, $superAdmin, $pinnedStudent, $otherStudents, $pinned, $approvedOthers);

        $this->markSomePayoutsPaid($payouts, $superAdmin);

        $this->command?->info('Demo data seeded: '.count($others) + 1 .' tutors, '.(count($otherStudents) + 1).' students, '.$allCourses->count().' self-paced courses.');
    }

    // ------------------------------------------------------------------
    // Admins
    // ------------------------------------------------------------------

    private function seedAdmins(): User
    {
        $superAdmin = User::factory()->admin()->create([
            'email' => 'admin@demo.co.za', 'first_name' => 'Demo', 'last_name' => 'SuperAdmin',
        ]);
        $superAdmin->forceFill(['is_super_admin' => true])->save();

        User::factory()->admin()->create(['email' => 'admin2@demo.co.za', 'first_name' => 'Sipho', 'last_name' => 'Nkosi']);
        User::factory()->admin()->create(['email' => 'admin3@demo.co.za', 'first_name' => 'Aisha', 'last_name' => 'Patel']);

        return $superAdmin;
    }

    // ------------------------------------------------------------------
    // Tutors
    // ------------------------------------------------------------------

    /**
     * @return array{user: User, profile: TutorProfile, subjects: \Illuminate\Support\Collection}
     */
    private function seedPinnedTutor(): array
    {
        $user = User::factory()->tutor()->create([
            'email' => 'magwenzilierance@gmail.com', 'first_name' => 'Magwenzi', 'last_name' => 'Lierance',
        ]);

        $subjects = $this->subjects->random(4);

        $profile = TutorProfile::create([
            'user_id' => $user->id,
            'display_name' => 'Magwenzi Lierance',
            'bio' => $this->bio($subjects),
            'years_experience' => 9,
            'occupation' => 'Full-time Tutor',
            'languages' => ['English', 'Zulu'],
            'teaching_style' => 'Patient, example-driven, and focused on building real exam confidence.',
            'about_me' => fake()->paragraph(4),
            'why_choose_me' => fake()->paragraph(3),
            'onboarding_step' => 6,
            'onboarding_complete' => true,
        ]);

        $profile->bankAccount()->create([
            'bank_name' => 'Standard Bank', 'account_holder_name' => 'Magwenzi Lierance',
            'account_number' => '10234567890', 'branch_code' => '051001', 'account_type' => 'savings',
        ]);

        foreach ($subjects as $subject) {
            $tutorSubject = TutorSubject::create(['tutor_profile_id' => $profile->id, 'subject_id' => $subject->id, 'status' => 'approved']);
            foreach ($this->grades->random(random_int(2, $this->grades->count())) as $grade) {
                $tutorSubject->tutorSubjectGrades()->create(['grade_id' => $grade->id]);
            }
        }

        $this->seedServices($profile, $subjects, 3);
        $this->seedAvailability($profile);

        return ['user' => $user, 'profile' => $profile, 'subjects' => $subjects];
    }

    /**
     * @return list<array{user: User, profile: TutorProfile, subjects: \Illuminate\Support\Collection}>
     */
    private function seedOtherTutors(int $count, TutorApprovalService $tutorApproval, TutorSubjectApprovalService $subjectApproval, User $admin): array
    {
        $statuses = array_merge(
            array_fill(0, 16, UserStatus::Approved),
            array_fill(0, 5, UserStatus::Pending),
            array_fill(0, 3, UserStatus::Rejected),
        );
        shuffle($statuses);

        $out = [];
        $subjectApprovalDemoCount = 0;

        for ($i = 0; $i < $count; $i++) {
            $status = $statuses[$i];
            $user = User::factory()->tutor()->create(['status' => $status]);
            $tutorSubjects = $this->subjects->random(random_int(1, 3));

            $profile = TutorProfile::create([
                'user_id' => $user->id,
                'display_name' => "{$user->first_name} {$user->last_name}",
                'bio' => $this->bio($tutorSubjects),
                'years_experience' => random_int(1, 15),
                'occupation' => fake()->randomElement(['Full-time Tutor', 'University Lecturer', 'High School Teacher', 'Part-time Tutor & Postgraduate Student']),
                'languages' => fake()->randomElements(['English', 'Afrikaans', 'Zulu', 'Xhosa', 'Sesotho', 'Tswana'], random_int(1, 3)),
                'teaching_style' => fake()->sentence(12),
                'about_me' => fake()->paragraph(3),
                'why_choose_me' => fake()->paragraph(2),
                'onboarding_step' => 6,
                'onboarding_complete' => true,
            ]);

            // ~5 pending tutors deliberately have no bank account, to demo
            // the "can't approve without banking details" blocker. Everyone
            // else gets one so some pending tutors can be approved live.
            $skipBankAccount = $status === UserStatus::Pending && random_int(1, 5) <= 4;
            if (! $skipBankAccount) {
                $profile->bankAccount()->create([
                    'bank_name' => fake()->randomElement(['FNB', 'Standard Bank', 'Absa', 'Nedbank', 'Capitec']),
                    'account_holder_name' => "{$user->first_name} {$user->last_name}",
                    'account_number' => (string) fake()->numberBetween(1000000000, 9999999999),
                    'branch_code' => '051001',
                    'account_type' => 'savings',
                ]);
            }

            $tutorSubjectStatuses = ['approved', 'approved', 'approved', 'pending', 'rejected', 'suspended'];
            $createdTutorSubjects = [];
            foreach ($tutorSubjects as $subject) {
                $tsStatus = $status === UserStatus::Approved ? fake()->randomElement($tutorSubjectStatuses) : 'pending';
                $ts = TutorSubject::create(['tutor_profile_id' => $profile->id, 'subject_id' => $subject->id, 'status' => $tsStatus]);
                foreach ($this->grades->random(random_int(1, $this->grades->count())) as $grade) {
                    $ts->tutorSubjectGrades()->create(['grade_id' => $grade->id]);
                }
                $createdTutorSubjects[] = $ts;
            }

            // Drive a handful of tutor-subject approvals through the real
            // service so AdminActivityLog/notifications populate.
            if ($status === UserStatus::Approved && $subjectApprovalDemoCount < 3) {
                $pending = collect($createdTutorSubjects)->first(fn ($ts) => $ts->status === \App\Enums\TutorSubjectStatus::Pending);
                if ($pending) {
                    $subjectApproval->approve($pending->fresh(), $admin);
                    $subjectApprovalDemoCount++;
                }
            }

            if ($status === UserStatus::Approved) {
                $this->seedServices($profile, $tutorSubjects, random_int(1, 3));
                $this->seedAvailability($profile);
            }

            $out[] = ['user' => $user, 'profile' => $profile, 'subjects' => $tutorSubjects];
        }

        // Drive one pending-with-bank-account tutor through a live approval,
        // one through a rejection, so the admin activity log/notifications
        // for tutor approval have real examples too.
        $pendingWithBank = collect($out)->first(fn ($t) => $t['user']->status === UserStatus::Pending && $t['profile']->bankAccount()->exists());
        if ($pendingWithBank) {
            $tutorApproval->approve($pendingWithBank['user'], $admin);
        }
        $anotherPending = collect($out)->first(fn ($t) => $t['user']->fresh()->status === UserStatus::Pending && $t['profile']->bankAccount()->exists());
        if ($anotherPending) {
            $tutorApproval->reject($anotherPending['user'], $admin, 'Please add references and complete your qualification uploads.');
        }

        return $out;
    }

    private function seedServices(TutorProfile $profile, \Illuminate\Support\Collection $subjects, int $count): void
    {
        for ($s = 0; $s < $count; $s++) {
            $subject = $subjects->random();
            $category = $this->categories->random();
            $format = $this->formats->random();
            $isGroup = str_contains(strtolower($category->name), 'group') || str_contains(strtolower($category->name), 'study');

            $service = $profile->services()->create([
                'subject_id' => $subject->id,
                'service_category_id' => $category->id,
                'session_format_id' => $format->id,
                'title' => "{$subject->name} {$category->name}",
                'description' => fake()->paragraph(3),
                'price' => random_int(15, 60) * 10,
                'currency' => 'ZAR',
                'session_duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
                'sessions_included' => random_int(1, 8),
                'validity_period_days' => fake()->randomElement([14, 30, 60, 90]),
                'max_students_per_session' => $isGroup ? random_int(2, 6) : 1,
                'visibility' => 'published',
            ]);

            $service->learningResources()->attach($this->resources->random(random_int(1, 4))->pluck('id'));
            $service->assessmentTypes()->attach($this->assessmentTypes->random(random_int(1, 3))->pluck('id'));
            $service->curricula()->attach($this->curricula->random(random_int(1, 2))->pluck('id'));
        }
    }

    private function seedAvailability(TutorProfile $profile): void
    {
        $start = Carbon::today();
        $end = $start->copy()->addDays(45);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $availabilityDate = AvailabilityDate::create(['tutor_profile_id' => $profile->id, 'date' => $date->toDateString()]);
            $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '12:00']);
            $availabilityDate->slots()->create(['start_time' => '14:00', 'end_time' => '17:00']);
        }
    }

    private function bio(\Illuminate\Support\Collection $subjects): string
    {
        $names = $subjects->pluck('name')->implode(', ');

        return "Passionate and experienced tutor specialising in {$names}. ".fake()->sentence(15).' '.fake()->sentence(12);
    }

    // ------------------------------------------------------------------
    // Students
    // ------------------------------------------------------------------

    private function seedPinnedStudent(): User
    {
        return User::factory()->create([
            'email' => 'student@demo.co.za', 'first_name' => 'Demo', 'last_name' => 'Student', 'status' => UserStatus::Approved,
        ]);
    }

    // ------------------------------------------------------------------
    // Self-paced courses
    // ------------------------------------------------------------------

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\SelfPacedCourse>
     */
    private function seedPinnedTutorCourses(TutorProfile $profile): \Illuminate\Support\Collection
    {
        $subjects = $this->subjects->random(5);
        $courses = collect();

        $specs = [
            ['status' => 'published', 'visibility' => 'public', 'price' => null, 'currency' => null],
            ['status' => 'published', 'visibility' => 'public', 'price' => 349, 'currency' => 'ZAR'],
            ['status' => 'published', 'visibility' => 'public', 'price' => 499, 'currency' => 'ZAR'],
            ['status' => 'published', 'visibility' => 'public', 'price' => 599, 'currency' => 'ZAR'],
            ['status' => 'draft', 'visibility' => 'private', 'price' => 299, 'currency' => 'ZAR'],
        ];

        foreach ($subjects as $index => $subject) {
            $spec = $specs[$index];
            $course = $profile->selfPacedCourses()->create([
                'subject_id' => $subject->id,
                'grade_id' => $this->grades->random()->id,
                'title' => "{$subject->name} Mastery Course",
                'subtitle' => fake()->sentence(8),
                'description' => fake()->paragraph(4),
                'difficulty' => fake()->randomElement(['beginner', 'intermediate', 'advanced']),
                'language' => 'English',
                'estimated_duration_minutes' => random_int(120, 480),
                'learning_objectives' => [fake()->sentence(8), fake()->sentence(8), fake()->sentence(8)],
                'status' => $spec['status'],
                'visibility' => $spec['visibility'],
                'price' => $spec['price'],
                'currency' => $spec['currency'],
            ]);

            $this->buildCourseModules($course, $index === 1);
            $courses->push($course);
        }

        return $courses;
    }

    private function seedSimpleCourse(TutorProfile $profile): \App\Models\SelfPacedCourse
    {
        $subject = $this->subjects->random();

        $course = $profile->selfPacedCourses()->create([
            'subject_id' => $subject->id,
            'grade_id' => $this->grades->random()->id,
            'title' => "{$subject->name} Essentials",
            'subtitle' => fake()->sentence(8),
            'description' => fake()->paragraph(3),
            'difficulty' => 'beginner',
            'language' => 'English',
            'estimated_duration_minutes' => random_int(60, 180),
            'status' => 'published',
            'visibility' => 'public',
            'price' => fake()->randomElement([0, 149, 199, 249]),
            'currency' => 'ZAR',
        ]);

        $module = $course->modules()->create([
            'title' => 'Getting Started', 'position' => 0,
            'activity_completion_required' => true, 'assessment_completion_required' => false,
        ]);
        $module->activities()->create([
            'type' => 'rich_text', 'title' => 'Welcome', 'position' => 0, 'required' => true,
            'content' => ['html' => '<p>'.fake()->paragraph(3).'</p>'],
        ]);
        $module->activities()->create([
            'type' => 'reading', 'title' => 'Core Concepts', 'position' => 1, 'required' => true,
            'content' => ['instructions' => fake()->paragraph(4)],
        ]);

        return $course;
    }

    /**
     * Builds 2-3 modules: rich-text/reading/external-resource activities in
     * every module, plus a SurveyJS-backed assessment on the last module —
     * mirrors the working shape from CourseCompletionTest so the course
     * would actually pass SelfPacedPublishingService::canPublish().
     */
    private function buildCourseModules(\App\Models\SelfPacedCourse $course, bool $threeModules): void
    {
        $moduleCount = $threeModules ? 3 : 2;
        $activityTypes = ['rich_text', 'reading', 'external_resource'];

        for ($m = 0; $m < $moduleCount; $m++) {
            $isLast = $m === $moduleCount - 1;
            $module = $course->modules()->create([
                'title' => "Module ".($m + 1),
                'position' => $m,
                'activity_completion_required' => true,
                'assessment_completion_required' => $isLast,
            ]);

            $activityCount = random_int(2, 3);
            for ($a = 0; $a < $activityCount; $a++) {
                $type = $activityTypes[$a % count($activityTypes)];
                $content = match ($type) {
                    'rich_text' => ['html' => '<p>'.fake()->paragraph(3).'</p>'],
                    'reading' => ['instructions' => fake()->paragraph(4)],
                    default => ['url' => 'https://example.com/resource'],
                };
                $module->activities()->create([
                    'type' => $type, 'title' => fake()->sentence(4), 'position' => $a, 'required' => true, 'content' => $content,
                ]);
            }

            if ($isLast) {
                $survey = $course->tutorProfile->selfPacedSurveyContents()->create([
                    'title' => "{$course->title} Quiz Bank",
                    'grade_id' => $course->grade_id,
                    'subject_id' => $course->subject_id,
                    'curriculum_id' => $this->curricula->random()->id,
                ]);
                $survey->questions()->create([
                    'position' => 0, 'type' => 'radiogroup',
                    'definition' => ['title' => 'Which of these best applies here?', 'choices' => ['Option A', 'Option B', 'Option C'], 'correctAnswer' => 'Option B'],
                    'points' => 10,
                ]);

                $module->assessments()->create([
                    'assessment_type' => 'chapter_test', 'title' => 'Module Check', 'position' => 0, 'required' => true,
                    'passing_score' => 70, 'attempts_mode' => 'unlimited',
                    'provider' => 'surveyjs', 'provider_config' => ['survey_content_id' => $survey->id],
                ]);
            }
        }
    }

    /**
     * @return array{lessons: list<Lesson>}
     */
    private function seedCourseContent(TutorProfile $profile): array
    {
        $lessons = [];

        foreach (range(1, 2) as $n) {
            $subject = $this->subjects->random();
            $course = Course::create([
                'tutor_profile_id' => $profile->id,
                'curriculum_id' => $this->curricula->random()->id,
                'grade_id' => $this->grades->random()->id,
                'subject_id' => $subject->id,
                'title' => "{$subject->name} Tutoring Curriculum",
                'description' => fake()->paragraph(3),
                'estimated_duration_minutes' => 240,
                'difficulty' => 'intermediate',
                'language' => 'English',
                'status' => 'published',
            ]);

            foreach (range(1, 2) as $c) {
                $chapter = Chapter::create([
                    'course_id' => $course->id, 'title' => "Chapter {$c}", 'description' => fake()->sentence(10),
                    'position' => $c - 1, 'status' => 'published',
                ]);

                foreach (range(1, 2) as $l) {
                    $lesson = Lesson::create([
                        'chapter_id' => $chapter->id, 'title' => fake()->sentence(4),
                        'description' => fake()->sentence(12), 'estimated_duration_minutes' => 30,
                        'position' => $l - 1, 'status' => 'published',
                    ]);

                    LessonBlock::create([
                        'lesson_id' => $lesson->id, 'block_type' => 'rich_text', 'position' => 0,
                        'title' => 'Overview', 'content' => ['html' => '<p>'.fake()->paragraph(4).'</p>'],
                        'settings' => [], 'status' => 'published',
                    ]);

                    $lessons[] = $lesson;
                }
            }
        }

        return ['lessons' => $lessons];
    }

    // ------------------------------------------------------------------
    // Enrollments
    // ------------------------------------------------------------------

    private function enrollPinnedStudent(
        User $student,
        \Illuminate\Support\Collection $pinnedCourses,
        \Illuminate\Support\Collection $otherCourses,
        OrderService $orders,
        PaymentService $payments,
        CommissionSnapshotService $commission,
        EnrollmentService $enrollments,
        ActivityProgressService $activityProgress,
        SelfPacedAssessmentAttemptService $assessmentAttempts,
    ): void {
        $published = $pinnedCourses->filter(fn ($c) => $c->status === \App\Enums\SelfPacedCourseStatus::Published)->values();
        $toComplete = $published->first();
        $toHalfway = $published->skip(1)->first();
        $justEnrolled = $otherCourses->take(2);

        foreach ([$toComplete, $toHalfway, ...$justEnrolled] as $course) {
            if (! $course) {
                continue;
            }
            $this->purchaseCourse($student, $course, $orders, $payments, $commission, $enrollments);
        }

        if ($toComplete) {
            $enrollment = $enrollments->findAccessible($student, $toComplete);
            if ($enrollment) {
                foreach ($toComplete->modules()->with('activities', 'assessments.module')->get() as $module) {
                    foreach ($module->activities as $activity) {
                        $activityProgress->markComplete($enrollment, $activity);
                    }
                    foreach ($module->assessments as $assessment) {
                        $attempt = $assessmentAttempts->start($assessment, $enrollment);
                        $question = $assessment->provider_config['survey_content_id'] ?? null;
                        $assessmentAttempts->complete($attempt, ['question_'.\App\Models\SelfPacedSurveyContent::find($question)?->questions()->first()?->id => 'Option B'], $enrollment);
                    }
                }
            }
        }

        if ($toHalfway) {
            $enrollment = $enrollments->findAccessible($student, $toHalfway);
            $firstModule = $toHalfway->modules()->with('activities')->orderBy('position')->first();
            if ($enrollment && $firstModule) {
                foreach ($firstModule->activities as $activity) {
                    $activityProgress->markComplete($enrollment, $activity);
                }
            }
        }
    }

    private function enrollOtherStudents(
        \Illuminate\Support\Collection $students,
        \Illuminate\Support\Collection $courses,
        OrderService $orders,
        PaymentService $payments,
        CommissionSnapshotService $commission,
        EnrollmentService $enrollments,
    ): void {
        foreach ($courses as $course) {
            foreach ($students->random(min($students->count(), random_int(3, 8))) as $student) {
                $this->purchaseCourse($student, $course, $orders, $payments, $commission, $enrollments);
            }
        }
    }

    private function purchaseCourse(
        User $student,
        \App\Models\SelfPacedCourse $course,
        OrderService $orders,
        PaymentService $payments,
        CommissionSnapshotService $commission,
        EnrollmentService $enrollments,
    ): void {
        if ($enrollments->hasAccess($student, $course)) {
            return;
        }

        try {
            $order = $orders->createForCourse($student, $course);
        } catch (\RuntimeException) {
            return;
        }

        if ($order->status === OrderStatus::Paid) {
            // Free course — already activated synchronously by OrderService.
            return;
        }

        $payment = $order->latestPayment;
        $payment = $payments->markSuccessful($payment, 'DEMO-'.Str::upper(Str::random(10)), 'demo', []);
        $order->update(['status' => OrderStatus::Paid]);
        $commission->record($payment);
        $enrollments->activate($order->fresh('items'));
    }

    // ------------------------------------------------------------------
    // Bookings
    // ------------------------------------------------------------------

    private function seedPinnedStudentBookings(
        User $student,
        array $pinned,
        \Illuminate\Support\Collection $otherTutors,
        BookingAcceptanceService $acceptance,
        PaymentService $payments,
        CommissionSnapshotService $commission,
        BookingConfirmationService $confirmation,
        BookingChatService $chat,
        array $courseContent,
    ): array {
        $statuses = ['pending', 'rejected', 'awaiting_payment', 'confirmed', 'completed'];
        $confirmedBooking = null;

        foreach ($statuses as $status) {
            $service = $pinned['profile']->services()->inRandomOrder()->first();
            if (! $service) {
                continue;
            }
            $booking = $this->createBooking($student, $pinned['profile'], $service, $status, $acceptance, $payments, $commission, $confirmation);
            if ($status === 'confirmed') {
                $confirmedBooking = $booking;
            }
            if (in_array($status, ['pending', 'awaiting_payment'], true)) {
                $chat->send($booking, $student, "Hi, looking forward to the session on {$booking->date}!");
                $chat->send($booking, $pinned['user'], 'Looking forward to it too — see you then.');
            }
        }

        foreach ($otherTutors->take(2) as $tutor) {
            $service = $tutor['profile']->services()->inRandomOrder()->first();
            if (! $service) {
                continue;
            }
            $this->createBooking($student, $tutor['profile'], $service, fake()->randomElement(['confirmed', 'completed']), $acceptance, $payments, $commission, $confirmation);
        }

        if ($confirmedBooking) {
            $session = $confirmedBooking->teachingSessions()->first();
            $lesson = $courseContent['lessons'][0] ?? null;
            if ($session && $lesson) {
                SessionLesson::create(['teaching_session_id' => $session->id, 'lesson_id' => $lesson->id, 'position' => 0]);
            }
        }

        return ['confirmed_booking' => $confirmedBooking];
    }

    private function seedOtherBookings(
        \Illuminate\Support\Collection $students,
        \Illuminate\Support\Collection $tutors,
        BookingAcceptanceService $acceptance,
        PaymentService $payments,
        CommissionSnapshotService $commission,
        BookingConfirmationService $confirmation,
        BookingChatService $chat,
    ): void {
        $weighted = [
            'pending', 'pending',
            'rejected',
            'awaiting_payment', 'awaiting_payment',
            'confirmed', 'confirmed', 'confirmed',
            'completed', 'completed',
            'cancelled',
        ];

        for ($i = 0; $i < 28; $i++) {
            $student = $students->random();
            $tutor = $tutors->random();
            $service = $tutor['profile']->services()->inRandomOrder()->first();
            if (! $service) {
                continue;
            }
            $status = $weighted[array_rand($weighted)];
            $booking = $this->createBooking($student, $tutor['profile'], $service, $status, $acceptance, $payments, $commission, $confirmation);

            if ($status === 'pending' && random_int(1, 4) === 1) {
                $chat->send($booking, $student, 'Quick question — is this slot still available?');
            }
        }
    }

    private function createBooking(
        User $student,
        TutorProfile $tutorProfile,
        Service $service,
        string $target,
        BookingAcceptanceService $acceptance,
        PaymentService $payments,
        CommissionSnapshotService $commission,
        BookingConfirmationService $confirmation,
    ): Booking {
        $slot = AvailabilitySlot::whereHas('availabilityDate', fn ($q) => $q->where('tutor_profile_id', $tutorProfile->id)->where('date', '>=', Carbon::today()->toDateString()))
            ->inRandomOrder()->first();

        $date = $slot?->availabilityDate?->date ?? Carbon::today()->addDays(random_int(1, 30))->toDateString();
        $startTime = $slot?->start_time ?? '09:00';
        $endTime = $slot?->end_time ?? '10:00';

        $booking = Booking::create([
            'student_id' => $student->id, 'tutor_profile_id' => $tutorProfile->id, 'service_id' => $service->id,
            'availability_slot_id' => $slot?->id, 'date' => $date, 'start_time' => $startTime, 'end_time' => $endTime,
            'price' => $service->price, 'currency' => $service->currency, 'status' => BookingStatus::Pending,
            'message' => fake()->sentence(10),
        ]);

        if ($target === 'pending') {
            return $booking;
        }

        if ($target === 'rejected') {
            $booking->update(['status' => BookingStatus::Rejected]);

            return $booking;
        }

        if ($target === 'cancelled') {
            $booking->update(['status' => BookingStatus::Cancelled]);

            return $booking;
        }

        $booking = $acceptance->accept($booking);

        if ($target === 'awaiting_payment') {
            return $booking;
        }

        $order = $booking->order;
        $payment = $payments->createPendingForOrder($order);
        $payment = $payments->markSuccessful($payment, 'DEMO-'.Str::upper(Str::random(10)), 'demo', []);
        $order->update(['status' => OrderStatus::Paid]);
        $commission->record($payment);
        $confirmation->confirm($order->fresh('items'));
        $booking->refresh();

        $transaction = FinancialTransaction::where('order_id', $order->id)->first();

        if ($target === 'confirmed') {
            $sessionDate = Carbon::today()->addDays(random_int(3, 14))->toDateString();
            $session = TeachingSession::create([
                'tutor_profile_id' => $tutorProfile->id, 'service_id' => $service->id, 'availability_slot_id' => null,
                'date' => $sessionDate, 'start_time' => $startTime, 'end_time' => $endTime,
                'status' => SessionStatus::Scheduled,
            ]);
            $booking->teachingSessions()->attach($session->id);

            SessionMeeting::create([
                'teaching_session_id' => $session->id, 'provider' => ConnectedAccountProvider::Google,
                'meeting_id' => 'demo-'.$session->id, 'meeting_url' => 'https://meet.google.com/demo-'.$session->id,
                'organizer_email' => $tutorProfile->user->email,
                'starts_at' => Carbon::parse("{$sessionDate} {$startTime}"), 'ends_at' => Carbon::parse("{$sessionDate} {$endTime}"),
                'status' => MeetingStatus::Scheduled,
            ]);
        }

        if ($target === 'completed') {
            $session = TeachingSession::create([
                'tutor_profile_id' => $tutorProfile->id, 'service_id' => $service->id, 'availability_slot_id' => null,
                'date' => Carbon::today()->subDays(random_int(3, 30))->toDateString(), 'start_time' => $startTime, 'end_time' => $endTime,
                'status' => SessionStatus::Completed, 'completed_at' => now(),
            ]);
            $booking->teachingSessions()->attach($session->id);
            $booking->update(['status' => BookingStatus::Completed]);

            if ($transaction) {
                $this->payableTransactions[] = $transaction;
            }
        }

        return $booking->fresh();
    }

    // ------------------------------------------------------------------
    // Tickets & payouts
    // ------------------------------------------------------------------

    private function seedPaymentTickets(PaymentTicketService $service, User $admin, \Illuminate\Support\Collection $tutors): void
    {
        $transactions = FinancialTransaction::inRandomOrder()->limit(5)->get();
        $statuses = ['open', 'in_review', 'resolved', 'rejected'];

        foreach ($transactions as $index => $transaction) {
            $tutorProfile = $transaction->tutorProfile;
            if (! $tutorProfile) {
                continue;
            }
            $ticket = $service->raise($transaction, $tutorProfile, "I haven't received this payout yet — could you please check on it?");

            if ($index > 0) {
                $service->updateStatus($ticket, \App\Enums\PaymentTicketStatus::from($statuses[$index % count($statuses)]), $admin);
            }
            if ($index === 0) {
                $service->addComment($ticket, $admin, "We're looking into this now, thanks for flagging it.");
            }
        }
    }

    private function seedSupportTickets(SupportTicketService $service, User $admin, User $pinnedStudent, \Illuminate\Support\Collection $otherStudents, array $pinnedTutor, \Illuminate\Support\Collection $otherTutors): void
    {
        $raisers = collect([$pinnedStudent, $pinnedTutor['user']])->merge($otherStudents->take(2))->merge($otherTutors->take(2)->pluck('user'));
        $subjects = [
            "Can't upload my qualification document",
            'Payment reflecting incorrectly',
            'Question about course refund policy',
            'Video not loading in lesson',
            'How do I change my availability?',
            'Certificate download not working',
        ];
        $statuses = ['open', 'in_review', 'resolved', 'rejected'];

        foreach ($raisers->take(6) as $index => $raiser) {
            $ticket = $service->raise($raiser, $subjects[$index % count($subjects)], fake()->paragraph(3));

            if ($index > 0) {
                $service->updateStatus($ticket, \App\Enums\SupportTicketStatus::from($statuses[$index % count($statuses)]), $admin);
            }
            if ($index === 0) {
                $service->addComment($ticket, $admin, 'Thanks for reaching out — can you share a screenshot?');
                $service->addComment($ticket, $raiser, 'Sure, attaching now.');
            }
        }
    }

    private function markSomePayoutsPaid(PayoutService $service, User $admin): void
    {
        // Every FinancialTransaction from a course purchase is payout-eligible
        // immediately; booking-type ones only once Completed (tracked above).
        $courseTransactions = FinancialTransaction::where('product_type', ProductType::CourseOffering->value)
            ->where('payout_status', 'pending')->inRandomOrder()->limit(5)->get();

        $eligible = $courseTransactions->merge(collect($this->payableTransactions)->take(5));

        foreach ($eligible->unique('id') as $transaction) {
            $service->markPaid($transaction->fresh(), $admin);
        }
    }
}
