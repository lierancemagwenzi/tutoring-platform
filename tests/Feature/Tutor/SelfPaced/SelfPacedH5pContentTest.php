<?php

namespace Tests\Feature\Tutor\SelfPaced;

use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SelfPacedH5pContentTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(): User
    {
        $tutorUser = User::factory()->tutor()->create();
        TutorProfile::create(['onboarding_complete' => true, 'user_id' => $tutorUser->id, 'display_name' => 'Test Tutor']);

        return $tutorUser;
    }

    public function test_tutor_can_tag_h5p_content_for_self_paced_use(): void
    {
        $tutor = $this->tutor();
        Sanctum::actingAs($tutor);

        $response = $this->postJson('/api/tutor/self-paced-h5p-contents', [
            'h5p_content_id' => '42',
            'title' => 'Interactive Quiz',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('content.h5p_content_id', '42');
        $response->assertJsonPath('content.title', 'Interactive Quiz');
    }

    public function test_index_only_lists_this_tutors_tagged_content(): void
    {
        $tutor = $this->tutor();
        $tutor->tutorProfile->selfPacedH5pContents()->create(['h5p_content_id' => '1', 'title' => 'Mine']);

        $other = $this->tutor();
        $other->tutorProfile->selfPacedH5pContents()->create(['h5p_content_id' => '2', 'title' => 'Not Mine']);

        Sanctum::actingAs($tutor);
        $response = $this->getJson('/api/tutor/self-paced-h5p-contents');

        $response->assertOk();
        $response->assertJsonCount(1, 'contents');
        $response->assertJsonPath('contents.0.title', 'Mine');
    }

    public function test_tagging_the_same_content_id_again_updates_rather_than_duplicates(): void
    {
        $tutor = $this->tutor();
        Sanctum::actingAs($tutor);

        $this->postJson('/api/tutor/self-paced-h5p-contents', ['h5p_content_id' => '99', 'title' => 'First Title'])->assertCreated();
        $this->postJson('/api/tutor/self-paced-h5p-contents', ['h5p_content_id' => '99', 'title' => 'Updated Title'])->assertCreated();

        $this->assertDatabaseCount('self_paced_h5p_contents', 1);
        $this->assertDatabaseHas('self_paced_h5p_contents', ['h5p_content_id' => '99', 'title' => 'Updated Title']);
    }
}
