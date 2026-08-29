<?php

namespace Tests\Feature\Marketplace;

use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SessionFormat;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TutorMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    private Subject $otherSubject;

    private Grade $grade;

    private Grade $otherGrade;

    private ServiceCategory $category;

    private SessionFormat $sessionFormat;

    private Curriculum $curriculum;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = Subject::create(['name' => 'Mathematics', 'is_active' => true]);
        $this->otherSubject = Subject::create(['name' => 'English', 'is_active' => true]);
        $this->grade = Grade::create(['name' => 'Grade 11', 'level' => 11, 'is_active' => true]);
        $this->otherGrade = Grade::create(['name' => 'Grade 12', 'level' => 12, 'is_active' => true]);
        $this->category = ServiceCategory::create(['name' => 'Private Lesson', 'is_active' => true]);
        $this->sessionFormat = SessionFormat::create(['name' => 'Online', 'is_active' => true]);
        $this->curriculum = Curriculum::create(['name' => 'CAPS', 'is_active' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function actingStudent(): User
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        return $student;
    }

    private function createTutor(array $profileOverrides = []): TutorProfile
    {
        $user = User::factory()->tutor()->create();

        return TutorProfile::create(array_merge([
            'user_id' => $user->id,
            'display_name' => 'Test Tutor',
            'bio' => 'An experienced, patient tutor who loves helping students succeed.',
            'years_experience' => 5,
            'languages' => ['English'],
        ], $profileOverrides));
    }

    private function createService(TutorProfile $tutorProfile, array $overrides = []): Service
    {
        return $tutorProfile->services()->create(array_merge([
            'subject_id' => $this->subject->id,
            'service_category_id' => $this->category->id,
            'session_format_id' => $this->sessionFormat->id,
            'title' => 'Grade 12 Maths Private Lesson',
            'description' => 'Focused exam preparation and past paper practice.',
            'price' => 300,
            'currency' => 'ZAR',
            'session_duration_minutes' => 60,
            'sessions_included' => 4,
            'validity_period_days' => 30,
            'max_students_per_session' => 1,
            'visibility' => 'published',
        ], $overrides));
    }

    public function test_only_tutors_with_published_services_appear(): void
    {
        $this->actingStudent();

        $publishedTutor = $this->createTutor(['display_name' => 'Published Tutor']);
        $this->createService($publishedTutor);

        $draftTutor = $this->createTutor(['display_name' => 'Draft Tutor']);
        $this->createService($draftTutor, ['visibility' => 'draft']);

        $pausedTutor = $this->createTutor(['display_name' => 'Paused Tutor']);
        $this->createService($pausedTutor, ['visibility' => 'paused']);

        $this->createTutor(['display_name' => 'No Service Tutor']);

        $response = $this->getJson('/api/marketplace/tutors');

        $response->assertOk()->assertJsonCount(1, 'tutors');
        $response->assertJsonPath('tutors.0.display_name', 'Published Tutor');
    }

    public function test_search_matches_tutor_name(): void
    {
        $this->actingStudent();

        $tutor = $this->createTutor(['display_name' => 'Jane Smith']);
        $this->createService($tutor);

        $other = $this->createTutor(['display_name' => 'Bob Jones']);
        $this->createService($other);

        $response = $this->getJson('/api/marketplace/tutors?search=Jane');

        $response->assertOk()->assertJsonCount(1, 'tutors');
        $response->assertJsonPath('tutors.0.display_name', 'Jane Smith');
    }

    public function test_search_matches_subject_name(): void
    {
        $this->actingStudent();

        $tutor = $this->createTutor();
        $this->createService($tutor, ['subject_id' => $this->subject->id]);

        $other = $this->createTutor();
        $this->createService($other, ['subject_id' => $this->otherSubject->id]);

        $response = $this->getJson('/api/marketplace/tutors?search=Mathematics');

        $response->assertOk()->assertJsonCount(1, 'tutors');
    }

    public function test_search_matches_service_title(): void
    {
        $this->actingStudent();

        $tutor = $this->createTutor();
        $this->createService($tutor, ['title' => 'Exam Crash Course']);

        $other = $this->createTutor();
        $this->createService($other, ['title' => 'Homework Help']);

        $response = $this->getJson('/api/marketplace/tutors?search=Crash+Course');

        $response->assertOk()->assertJsonCount(1, 'tutors');
    }

    public function test_filter_by_subject(): void
    {
        $this->actingStudent();

        $mathsTutor = $this->createTutor();
        $this->createService($mathsTutor, ['subject_id' => $this->subject->id]);

        $englishTutor = $this->createTutor();
        $this->createService($englishTutor, ['subject_id' => $this->otherSubject->id]);

        $response = $this->getJson("/api/marketplace/tutors?subject_id={$this->subject->id}");

        $response->assertOk()->assertJsonCount(1, 'tutors');
    }

    public function test_filter_by_grade(): void
    {
        $this->actingStudent();

        $grade11Tutor = $this->createTutor();
        $this->createService($grade11Tutor, ['grade_id' => $this->grade->id]);

        $grade12Tutor = $this->createTutor();
        $this->createService($grade12Tutor, ['grade_id' => $this->otherGrade->id]);

        $response = $this->getJson("/api/marketplace/tutors?grade_id={$this->grade->id}");

        $response->assertOk()->assertJsonCount(1, 'tutors');
        $response->assertJsonPath('tutors.0.grades.0.name', 'Grade 11');
    }

    public function test_filter_by_price_range(): void
    {
        $this->actingStudent();

        $cheapTutor = $this->createTutor();
        $this->createService($cheapTutor, ['price' => 100]);

        $expensiveTutor = $this->createTutor();
        $this->createService($expensiveTutor, ['price' => 900]);

        $response = $this->getJson('/api/marketplace/tutors?price_min=50&price_max=200');

        $response->assertOk()->assertJsonCount(1, 'tutors');
        $response->assertJsonPath('tutors.0.starting_price', '100.00');
    }

    public function test_filter_by_years_experience(): void
    {
        $this->actingStudent();

        $junior = $this->createTutor(['years_experience' => 1]);
        $this->createService($junior);

        $senior = $this->createTutor(['years_experience' => 10]);
        $this->createService($senior);

        $response = $this->getJson('/api/marketplace/tutors?years_experience_min=5');

        $response->assertOk()->assertJsonCount(1, 'tutors');
        $response->assertJsonPath('tutors.0.years_experience', 10);
    }

    public function test_filter_by_language(): void
    {
        $this->actingStudent();

        $englishTutor = $this->createTutor(['languages' => ['English']]);
        $this->createService($englishTutor);

        $frenchTutor = $this->createTutor(['languages' => ['French']]);
        $this->createService($frenchTutor);

        $response = $this->getJson('/api/marketplace/tutors?language=French');

        $response->assertOk()->assertJsonCount(1, 'tutors');
    }

    public function test_sorting_lowest_price_first(): void
    {
        $this->actingStudent();

        $expensive = $this->createTutor(['display_name' => 'Expensive']);
        $this->createService($expensive, ['price' => 500]);

        $cheap = $this->createTutor(['display_name' => 'Cheap']);
        $this->createService($cheap, ['price' => 100]);

        $response = $this->getJson('/api/marketplace/tutors?sort=lowest_price');

        $response->assertOk();
        $response->assertJsonPath('tutors.0.display_name', 'Cheap');
        $response->assertJsonPath('tutors.1.display_name', 'Expensive');
    }

    public function test_sorting_most_experienced(): void
    {
        $this->actingStudent();

        $junior = $this->createTutor(['display_name' => 'Junior', 'years_experience' => 1]);
        $this->createService($junior);

        $senior = $this->createTutor(['display_name' => 'Senior', 'years_experience' => 15]);
        $this->createService($senior);

        $response = $this->getJson('/api/marketplace/tutors?sort=most_experienced');

        $response->assertOk();
        $response->assertJsonPath('tutors.0.display_name', 'Senior');
    }

    public function test_sorting_alphabetical(): void
    {
        $this->actingStudent();

        $z = $this->createTutor(['display_name' => 'Zoe']);
        $this->createService($z);

        $a = $this->createTutor(['display_name' => 'Amy']);
        $this->createService($a);

        $response = $this->getJson('/api/marketplace/tutors?sort=alphabetical');

        $response->assertOk();
        $response->assertJsonPath('tutors.0.display_name', 'Amy');
    }

    public function test_pagination_meta(): void
    {
        $this->actingStudent();

        for ($i = 0; $i < 15; $i++) {
            $tutor = $this->createTutor(['display_name' => "Tutor {$i}"]);
            $this->createService($tutor);
        }

        $response = $this->getJson('/api/marketplace/tutors?per_page=10');

        $response->assertOk()->assertJsonCount(10, 'tutors');
        $response->assertJsonPath('meta.total', 15);
        $response->assertJsonPath('meta.last_page', 2);
    }

    public function test_show_returns_only_published_services(): void
    {
        $this->actingStudent();

        $tutor = $this->createTutor();
        $this->createService($tutor, ['title' => 'Published Service']);
        $this->createService($tutor, ['title' => 'Draft Service', 'visibility' => 'draft']);

        $response = $this->getJson("/api/marketplace/tutors/{$tutor->id}");

        $response->assertOk()->assertJsonCount(1, 'tutor.services');
        $response->assertJsonPath('tutor.services.0.title', 'Published Service');
    }

    public function test_show_404s_for_nonexistent_tutor(): void
    {
        $this->actingStudent();

        $response = $this->getJson('/api/marketplace/tutors/999999');

        $response->assertNotFound();
    }
}
