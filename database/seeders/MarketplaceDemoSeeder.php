<?php

namespace Database\Seeders;

use App\Models\AssessmentType;
use App\Models\AvailabilityDate;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\LearningResource;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\TutorSubject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MarketplaceDemoSeeder extends Seeder
{
    /**
     * The number of demo tutors to generate.
     */
    private const TUTOR_COUNT = 30;

    /**
     * @var list<string>
     */
    private const LANGUAGE_POOL = ['English', 'Afrikaans', 'Zulu', 'Xhosa', 'Sesotho', 'Tswana'];

    /**
     * @var list<string>
     */
    private const OCCUPATIONS = [
        'Full-time Tutor',
        'University Lecturer',
        'High School Teacher',
        'Part-time Tutor & Postgraduate Student',
    ];

    /**
     * Run the database seeds.
     *
     * Populates the marketplace with demo tutors who each have approved subjects,
     * published services, and teaching availability from today through the end of July,
     * so the Marketplace/Booking flows have realistic data to browse and book against.
     */
    public function run(): void
    {
        $this->call([
            GradeSeeder::class,
            SubjectSeeder::class,
            ServiceCategorySeeder::class,
            SessionFormatSeeder::class,
            LearningResourceSeeder::class,
            AssessmentTypeSeeder::class,
            CurriculumSeeder::class,
        ]);

        $subjects = Subject::where('is_active', true)->get();
        $grades = Grade::where('is_active', true)->get();
        $categories = ServiceCategory::where('is_active', true)->get();
        $sessionFormats = SessionFormat::where('is_active', true)->get();
        $learningResources = LearningResource::where('is_active', true)->get();
        $assessmentTypes = AssessmentType::where('is_active', true)->get();
        $curricula = Curriculum::where('is_active', true)->get();

        $startDate = Carbon::today();
        $endDate = Carbon::create($startDate->year, 7, 31);

        for ($i = 0; $i < self::TUTOR_COUNT; $i++) {
            $user = User::factory()->tutor()->create();
            $tutorSubjects = $subjects->random(random_int(1, 3));

            $profile = TutorProfile::create([
                'user_id' => $user->id,
                'display_name' => "{$user->first_name} {$user->last_name}",
                'bio' => $this->generateBio($tutorSubjects),
                'profile_photo' => null,
                'years_experience' => random_int(1, 15),
                'occupation' => fake()->randomElement(self::OCCUPATIONS),
                'languages' => fake()->randomElements(self::LANGUAGE_POOL, random_int(1, 3)),
                'teaching_style' => fake()->sentence(12),
                'about_me' => fake()->paragraph(3),
                'why_choose_me' => fake()->paragraph(2),
                'onboarding_step' => 6,
                'onboarding_complete' => true,
            ]);

            foreach ($tutorSubjects as $subject) {
                $tutorSubject = TutorSubject::create([
                    'tutor_profile_id' => $profile->id,
                    'subject_id' => $subject->id,
                    'status' => 'approved',
                ]);

                foreach ($grades->random(random_int(1, $grades->count())) as $grade) {
                    $tutorSubject->tutorSubjectGrades()->create(['grade_id' => $grade->id]);
                }
            }

            $serviceCount = random_int(1, 3);

            for ($s = 0; $s < $serviceCount; $s++) {
                $subject = $tutorSubjects->random();
                $category = $categories->random();
                $format = $sessionFormats->random();
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

                $service->learningResources()->attach($learningResources->random(random_int(1, 4))->pluck('id'));
                $service->assessmentTypes()->attach($assessmentTypes->random(random_int(1, 3))->pluck('id'));
                $service->curricula()->attach($curricula->random(random_int(1, 2))->pluck('id'));
            }

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $availabilityDate = AvailabilityDate::create([
                    'tutor_profile_id' => $profile->id,
                    'date' => $date->toDateString(),
                ]);

                $availabilityDate->slots()->create(['start_time' => '09:00', 'end_time' => '12:00']);
                $availabilityDate->slots()->create(['start_time' => '14:00', 'end_time' => '17:00']);
            }
        }
    }

    /**
     * Generate a realistic bio referencing the tutor's subjects.
     */
    private function generateBio($subjects): string
    {
        $subjectNames = $subjects->pluck('name')->implode(', ');

        return "Passionate and experienced tutor specialising in {$subjectNames}. "
            .fake()->sentence(15)
            .' '.fake()->sentence(12);
    }
}
