<?php

namespace Tests\Feature\Commerce;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SelfPacedCourse;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateOrderTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    private function publishedCourse(TutorProfile $tutor, array $overrides = []): SelfPacedCourse
    {
        return $tutor->selfPacedCourses()->create(array_merge([
            'title' => 'Test Course',
            'price' => 200,
            'currency' => 'ZAR',
            'status' => 'published',
            'visibility' => 'public',
        ], $overrides));
    }

    public function test_student_can_create_order_for_paid_course(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $tutor = $this->tutor();
        $course = $this->publishedCourse($tutor, ['price' => 200]);
        $course->discountCodes()->create(['code' => 'SAVE10', 'discount_type' => 'percentage', 'discount_value' => 10]);

        $response = $this->postJson('/api/orders', ['course_offering_id' => $course->id]);

        $response->assertCreated();
        $response->assertJsonPath('order.status', 'pending');
        $response->assertJsonPath('order.total_amount', '200.00');
        $response->assertJsonPath('order.discount_amount', '20.00');
        $response->assertJsonPath('order.final_amount', '180.00');
        $response->assertJsonPath('order.items.0.product.id', $course->id);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('enrollments', 0);

        $payment = Payment::first();
        $this->assertSame('pending', $payment->status->value);
        $this->assertSame('180.00', (string) $payment->amount);
    }

    public function test_free_course_order_is_immediately_marked_paid_and_enrolls_student(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $tutor = $this->tutor();
        $course = $this->publishedCourse($tutor, ['price' => 0]);

        $response = $this->postJson('/api/orders', ['course_offering_id' => $course->id]);

        $response->assertCreated();
        $response->assertJsonPath('order.status', 'paid');
        $response->assertJsonPath('order.final_amount', '0.00');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('enrollments', 1);

        $enrollment = Enrollment::first();
        $this->assertSame($student->id, $enrollment->student_id);
        $this->assertSame($course->id, $enrollment->self_paced_course_id);
        $this->assertSame('active', $enrollment->status->value);
    }

    public function test_order_creation_blocks_duplicate_active_enrollment(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $tutor = $this->tutor();
        $course = $this->publishedCourse($tutor);

        Enrollment::create([
            'student_id' => $student->id,
            'self_paced_course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $response = $this->postJson('/api/orders', ['course_offering_id' => $course->id]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['course_offering_id']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cannot_create_order_for_draft_course(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $tutor = $this->tutor();
        $course = $this->publishedCourse($tutor, ['status' => 'draft']);

        $response = $this->postJson('/api/orders', ['course_offering_id' => $course->id]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['course_offering_id']);
    }

    public function test_cannot_create_order_for_private_visibility_course(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $tutor = $this->tutor();
        $course = $this->publishedCourse($tutor, ['visibility' => 'private']);

        $response = $this->postJson('/api/orders', ['course_offering_id' => $course->id]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['course_offering_id']);
    }

    public function test_guest_cannot_create_order(): void
    {
        $tutor = $this->tutor();
        $course = $this->publishedCourse($tutor);

        $response = $this->postJson('/api/orders', ['course_offering_id' => $course->id]);

        $response->assertUnauthorized();
        $this->assertDatabaseCount('orders', 0);
    }
}
