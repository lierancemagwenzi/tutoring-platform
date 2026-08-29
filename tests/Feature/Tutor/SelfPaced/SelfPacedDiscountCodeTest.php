<?php

namespace Tests\Feature\Tutor\SelfPaced;

use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SelfPacedDiscountCodeTest extends TestCase
{
    use RefreshDatabase;

    private function tutorWithPricedCourse(): array
    {
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);
        $course = $tutorUser->tutorProfile->selfPacedCourses()->create([
            'title' => 'Priced Course',
            'price' => 200,
            'currency' => 'USD',
        ]);

        return [$tutorUser, $course];
    }

    public function test_tutor_can_create_a_percentage_discount_code(): void
    {
        [$tutor, $course] = $this->tutorWithPricedCourse();
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/self-paced-courses/{$course->id}/discount-codes", [
            'code' => 'launch20',
            'discount_type' => 'percentage',
            'discount_value' => 20,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('discount_code.code', 'LAUNCH20');
        $response->assertJsonPath('discount_code.is_valid_now', true);
    }

    public function test_percentage_discount_cannot_exceed_100(): void
    {
        [$tutor, $course] = $this->tutorWithPricedCourse();
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/self-paced-courses/{$course->id}/discount-codes", [
            'code' => 'TOOMUCH',
            'discount_type' => 'percentage',
            'discount_value' => 150,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('discount_value');
    }

    public function test_preview_computes_the_discounted_price(): void
    {
        [$tutor, $course] = $this->tutorWithPricedCourse();
        $course->discountCodes()->create([
            'code' => 'SAVE25',
            'discount_type' => 'fixed_amount',
            'discount_value' => 25,
        ]);
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/self-paced-courses/{$course->id}/discount-codes/preview", [
            'code' => 'save25',
        ]);

        $response->assertOk();
        $response->assertJsonPath('valid', true);
        $response->assertJsonPath('original_price', '200.00');
        $response->assertJsonPath('discounted_price', '175.00');
    }

    public function test_preview_rejects_an_expired_code(): void
    {
        [$tutor, $course] = $this->tutorWithPricedCourse();
        $course->discountCodes()->create([
            'code' => 'EXPIRED',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'expires_at' => Carbon::now()->subDay(),
        ]);
        Sanctum::actingAs($tutor);

        $response = $this->postJson("/api/tutor/self-paced-courses/{$course->id}/discount-codes/preview", [
            'code' => 'EXPIRED',
        ]);

        $response->assertOk();
        $response->assertJsonPath('valid', false);
    }

    public function test_discount_code_cannot_exceed_redemption_cap(): void
    {
        [$tutor, $course] = $this->tutorWithPricedCourse();
        $code = $course->discountCodes()->create([
            'code' => 'LIMITED',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'max_redemptions' => 1,
            'times_redeemed' => 1,
        ]);

        $this->assertFalse($code->isValidNow());
    }

    public function test_tutor_cannot_manage_another_tutors_discount_codes(): void
    {
        [, $course] = $this->tutorWithPricedCourse();

        $intruder = User::factory()->tutor()->create();
        TutorProfile::create(['user_id' => $intruder->id, 'display_name' => 'Intruder']);
        Sanctum::actingAs($intruder);

        $this->postJson("/api/tutor/self-paced-courses/{$course->id}/discount-codes", [
            'code' => 'HACK', 'discount_type' => 'percentage', 'discount_value' => 10,
        ])->assertForbidden();
    }
}
