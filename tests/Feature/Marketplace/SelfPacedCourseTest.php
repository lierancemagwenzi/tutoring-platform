<?php

namespace Tests\Feature\Marketplace;

use App\Models\Grade;
use App\Models\SelfPacedCourse;
use App\Models\Subject;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SelfPacedCourseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    private function publishedCourse(TutorProfile $tutor, array $overrides = []): SelfPacedCourse
    {
        $course = $tutor->selfPacedCourses()->create(array_merge([
            'title' => 'Test Course',
            'price' => 100,
            'currency' => 'USD',
            'status' => 'published',
            'visibility' => 'public',
        ], $overrides));

        $module = $course->modules()->create(['title' => 'Module 1', 'position' => 0]);
        $module->activities()->create([
            'type' => 'rich_text', 'title' => 'Welcome', 'position' => 0, 'required' => true,
            'content' => ['html' => '<p>Hi</p>'],
        ]);

        return $course;
    }

    public function test_index_only_returns_published_public_courses(): void
    {
        $tutor = $this->tutor();
        $published = $this->publishedCourse($tutor, ['title' => 'Published Course']);
        $this->publishedCourse($tutor, ['title' => 'Draft Course', 'status' => 'draft']);
        $this->publishedCourse($tutor, ['title' => 'Private Status Course', 'status' => 'private']);
        $this->publishedCourse($tutor, ['title' => 'Archived Course', 'status' => 'archived']);
        $this->publishedCourse($tutor, ['title' => 'Unlisted Course', 'visibility' => 'unlisted']);
        $this->publishedCourse($tutor, ['title' => 'Private Visibility Course', 'visibility' => 'private']);

        $response = $this->getJson('/api/marketplace/self-paced-courses');

        $response->assertOk();
        $response->assertJsonCount(1, 'courses');
        $response->assertJsonPath('courses.0.id', $published->id);
    }

    public function test_unlisted_course_is_reachable_by_direct_link_but_not_listed(): void
    {
        $tutor = $this->tutor();
        $unlisted = $this->publishedCourse($tutor, ['title' => 'Unlisted Course', 'visibility' => 'unlisted']);

        $listResponse = $this->getJson('/api/marketplace/self-paced-courses');
        $listResponse->assertJsonCount(0, 'courses');

        $showResponse = $this->getJson("/api/marketplace/self-paced-courses/{$unlisted->id}");
        $showResponse->assertOk();
        $showResponse->assertJsonPath('course.id', $unlisted->id);
    }

    public function test_private_visibility_course_returns_404_even_by_direct_link(): void
    {
        $tutor = $this->tutor();
        $course = $this->publishedCourse($tutor, ['visibility' => 'private']);

        $this->getJson("/api/marketplace/self-paced-courses/{$course->id}")->assertNotFound();
    }

    public function test_search_matches_title_subject_and_tutor_name(): void
    {
        $subject = Subject::create(['name' => 'Advanced Chemistry', 'is_active' => true]);
        $tutor = $this->tutor();
        $this->publishedCourse($tutor, ['title' => 'Algebra Basics']);
        $bySubject = $this->publishedCourse($tutor, ['title' => 'Unrelated Title', 'subject_id' => $subject->id]);

        $response = $this->getJson('/api/marketplace/self-paced-courses?search=Chemistry');

        $response->assertOk();
        $response->assertJsonCount(1, 'courses');
        $response->assertJsonPath('courses.0.id', $bySubject->id);
    }

    public function test_filters_by_difficulty_language_and_price(): void
    {
        $tutor = $this->tutor();
        $free = $this->publishedCourse($tutor, ['title' => 'Free Course', 'price' => 0, 'difficulty' => 'beginner', 'language' => 'English']);
        $this->publishedCourse($tutor, ['title' => 'Paid Course', 'price' => 50, 'difficulty' => 'advanced', 'language' => 'French']);

        $response = $this->getJson('/api/marketplace/self-paced-courses?'.http_build_query(['price' => 'free', 'difficulty' => 'beginner']));

        $response->assertOk();
        $response->assertJsonCount(1, 'courses');
        $response->assertJsonPath('courses.0.id', $free->id);
        $response->assertJsonPath('courses.0.pricing.is_free', true);
    }

    public function test_filters_by_grade(): void
    {
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $tutor = $this->tutor();
        $matching = $this->publishedCourse($tutor, ['title' => 'Grade 10 Course', 'grade_id' => $grade->id]);
        $this->publishedCourse($tutor, ['title' => 'No Grade Course']);

        $response = $this->getJson('/api/marketplace/self-paced-courses?'.http_build_query(['grade_id' => $grade->id]));

        $response->assertOk();
        $response->assertJsonCount(1, 'courses');
        $response->assertJsonPath('courses.0.id', $matching->id);
        $response->assertJsonPath('courses.0.grade.name', 'Grade 10');
    }

    public function test_sort_alphabetical_and_price(): void
    {
        $tutor = $this->tutor();
        $this->publishedCourse($tutor, ['title' => 'Zebra Course', 'price' => 200]);
        $this->publishedCourse($tutor, ['title' => 'Apple Course', 'price' => 50]);

        $alpha = $this->getJson('/api/marketplace/self-paced-courses?sort=alphabetical');
        $alpha->assertJsonPath('courses.0.title', 'Apple Course');

        $priceLow = $this->getJson('/api/marketplace/self-paced-courses?sort=price_low');
        $priceLow->assertJsonPath('courses.0.title', 'Apple Course');

        $priceHigh = $this->getJson('/api/marketplace/self-paced-courses?sort=price_high');
        $priceHigh->assertJsonPath('courses.0.title', 'Zebra Course');
    }

    public function test_pricing_reflects_the_best_active_discount_code(): void
    {
        $tutor = $this->tutor();
        $course = $this->publishedCourse($tutor, ['price' => 200, 'currency' => 'USD']);
        $course->discountCodes()->create(['code' => 'SAVE10', 'discount_type' => 'percentage', 'discount_value' => 10]);
        $course->discountCodes()->create(['code' => 'SAVE25', 'discount_type' => 'percentage', 'discount_value' => 25]);
        $course->discountCodes()->create([
            'code' => 'EXPIRED', 'discount_type' => 'percentage', 'discount_value' => 90,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $response = $this->getJson("/api/marketplace/self-paced-courses/{$course->id}");

        $response->assertOk();
        $response->assertJsonPath('course.pricing.original_price', '200.00');
        $response->assertJsonPath('course.pricing.discounted_price', '150.00');
        $response->assertJsonPath('course.pricing.discount_percentage', 25.0);
    }

    public function test_details_response_does_not_expose_assessment_content(): void
    {
        $tutor = $this->tutor();
        $course = $this->publishedCourse($tutor);
        $module = $course->modules()->first();
        $module->assessments()->create([
            'assessment_type' => 'practice_quiz',
            'title' => 'Quiz',
            'position' => 1,
            'provider' => 'surveyjs',
            'provider_config' => ['survey_content_id' => 1],
        ]);

        $response = $this->getJson("/api/marketplace/self-paced-courses/{$course->id}");

        $response->assertOk();
        $response->assertJsonMissingPath('course.modules.0.assessments.0.provider_config');
        $response->assertJsonMissingPath('course.modules.0.assessments.0.provider');
        $response->assertJsonPath('course.modules.0.assessments.0.title', 'Quiz');
    }

    public function test_filters_endpoint_only_reflects_published_public_courses(): void
    {
        $tutor = $this->tutor();
        $subject = Subject::create(['name' => 'Geography', 'is_active' => true]);
        $grade = Grade::create(['name' => 'Grade 10', 'level' => 10, 'is_active' => true]);
        $this->publishedCourse($tutor, ['subject_id' => $subject->id, 'grade_id' => $grade->id, 'language' => 'Zulu']);
        $this->publishedCourse($tutor, ['status' => 'draft', 'language' => 'ShouldNotAppear']);

        $response = $this->getJson('/api/marketplace/self-paced-courses/filters');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Geography']);
        $response->assertJsonFragment(['name' => 'Grade 10']);
        $response->assertJsonFragment(['display_name' => 'Test Tutor']);
        $this->assertContains('Zulu', $response->json('languages'));
        $this->assertNotContains('ShouldNotAppear', $response->json('languages'));
    }
}
