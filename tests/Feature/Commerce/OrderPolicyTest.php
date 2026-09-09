<?php

namespace Tests\Feature\Commerce;

use App\Models\TutorProfile;
use App\Models\User;
use App\Services\Commerce\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): TutorProfile
    {
        $tutorUser = User::factory()->tutor()->create();

        return TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
    }

    public function test_student_cannot_view_another_students_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $tutor = $this->tutor();
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Test Course', 'price' => 100, 'currency' => 'ZAR', 'status' => 'published', 'visibility' => 'public',
        ]);

        $order = app(OrderService::class)->createForCourse($owner, $course);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/orders/{$order->id}")->assertForbidden();
    }

    public function test_student_can_view_own_order_with_payment_details(): void
    {
        $student = User::factory()->create();

        $tutor = $this->tutor();
        $course = $tutor->selfPacedCourses()->create([
            'title' => 'Test Course', 'price' => 100, 'currency' => 'ZAR', 'status' => 'published', 'visibility' => 'public',
        ]);

        $order = app(OrderService::class)->createForCourse($student, $course);

        Sanctum::actingAs($student);

        $response = $this->getJson("/api/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('order.id', $order->id);
        $response->assertJsonPath('order.payment.status', 'pending');
    }
}
