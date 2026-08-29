<?php

namespace Tests\Feature\Admin;

use App\Models\Faq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FaqTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_create_a_faq(): void
    {
        $this->admin();

        $response = $this->postJson('/api/admin/faqs', [
            'question' => 'How do I reset my password?',
            'answer' => 'Use the forgot password link on the login screen.',
            'audience' => 'both',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('faq.question', 'How do I reset my password?');
        $response->assertJsonPath('faq.audience', 'both');
        $response->assertJsonPath('faq.is_published', true);
        $this->assertDatabaseHas('faqs', ['question' => 'How do I reset my password?']);
    }

    public function test_creating_a_faq_rejects_an_invalid_audience(): void
    {
        $this->admin();

        $response = $this->postJson('/api/admin/faqs', [
            'question' => 'Q', 'answer' => 'A', 'audience' => 'everyone',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('audience');
    }

    public function test_admin_can_update_a_faq(): void
    {
        $this->admin();
        $faq = Faq::create(['question' => 'Old Q', 'answer' => 'Old A', 'audience' => 'student']);

        $response = $this->patchJson("/api/admin/faqs/{$faq->id}", [
            'question' => 'New Q', 'is_published' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('faq.question', 'New Q');
        $response->assertJsonPath('faq.is_published', false);
        $response->assertJsonPath('faq.answer', 'Old A');
    }

    public function test_admin_can_delete_a_faq(): void
    {
        $this->admin();
        $faq = Faq::create(['question' => 'Q', 'answer' => 'A', 'audience' => 'both']);

        $response = $this->deleteJson("/api/admin/faqs/{$faq->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('faqs', ['id' => $faq->id]);
    }

    public function test_admin_can_filter_faqs_by_audience(): void
    {
        $this->admin();
        Faq::create(['question' => 'Student Q', 'answer' => 'A', 'audience' => 'student']);
        Faq::create(['question' => 'Tutor Q', 'answer' => 'A', 'audience' => 'tutor']);

        $response = $this->getJson('/api/admin/faqs?audience=tutor');

        $response->assertOk();
        $response->assertJsonCount(1, 'faqs');
        $response->assertJsonPath('faqs.0.question', 'Tutor Q');
    }

    public function test_non_admin_cannot_manage_faqs(): void
    {
        $tutorUser = User::factory()->tutor()->create();
        Sanctum::actingAs($tutorUser);

        $this->postJson('/api/admin/faqs', ['question' => 'Q', 'answer' => 'A', 'audience' => 'both'])->assertForbidden();
    }
}
