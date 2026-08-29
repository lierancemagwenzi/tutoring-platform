<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FaqTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tutor_sees_tutor_and_both_faqs_but_not_student_only(): void
    {
        Faq::create(['question' => 'Student Q', 'answer' => 'A', 'audience' => 'student']);
        Faq::create(['question' => 'Tutor Q', 'answer' => 'A', 'audience' => 'tutor']);
        Faq::create(['question' => 'Both Q', 'answer' => 'A', 'audience' => 'both']);

        $tutorUser = User::factory()->tutor()->create();
        Sanctum::actingAs($tutorUser);

        $response = $this->getJson('/api/faqs');

        $response->assertOk();
        $response->assertJsonCount(2, 'faqs');
        $questions = collect($response->json('faqs'))->pluck('question');
        $this->assertTrue($questions->contains('Tutor Q'));
        $this->assertTrue($questions->contains('Both Q'));
        $this->assertFalse($questions->contains('Student Q'));
    }

    public function test_a_student_sees_student_and_both_faqs_but_not_tutor_only(): void
    {
        Faq::create(['question' => 'Student Q', 'answer' => 'A', 'audience' => 'student']);
        Faq::create(['question' => 'Tutor Q', 'answer' => 'A', 'audience' => 'tutor']);
        Faq::create(['question' => 'Both Q', 'answer' => 'A', 'audience' => 'both']);

        $studentUser = User::factory()->create();
        Sanctum::actingAs($studentUser);

        $response = $this->getJson('/api/faqs');

        $response->assertOk();
        $response->assertJsonCount(2, 'faqs');
        $questions = collect($response->json('faqs'))->pluck('question');
        $this->assertTrue($questions->contains('Student Q'));
        $this->assertTrue($questions->contains('Both Q'));
        $this->assertFalse($questions->contains('Tutor Q'));
    }

    public function test_unpublished_faqs_are_hidden_from_both_roles(): void
    {
        Faq::create(['question' => 'Hidden Q', 'answer' => 'A', 'audience' => 'both', 'is_published' => false]);

        $studentUser = User::factory()->create();
        Sanctum::actingAs($studentUser);

        $response = $this->getJson('/api/faqs');

        $response->assertOk();
        $response->assertJsonCount(0, 'faqs');
    }
}
