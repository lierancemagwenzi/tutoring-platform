<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivityLog;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GradeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_add_a_grade_and_it_appears_in_the_public_list(): void
    {
        $this->admin();

        $this->postJson('/api/admin/grades', ['name' => 'Grade 8', 'level' => 8, 'description' => 'Senior phase'])
            ->assertCreated()
            ->assertJsonPath('grade.name', 'Grade 8')
            ->assertJsonPath('grade.is_active', true);

        $this->getJson('/api/grades')->assertOk()->assertJsonPath('grades.0.name', 'Grade 8');
        $this->assertSame(1, AdminActivityLog::where('action', 'grade.created')->count());
    }

    public function test_admin_lists_all_grades_ordered_by_level_including_inactive(): void
    {
        $this->admin();
        Grade::create(['name' => 'Grade 12', 'level' => 12, 'is_active' => true]);
        Grade::create(['name' => 'Grade 1', 'level' => 1, 'is_active' => false]);

        $this->getJson('/api/admin/grades')
            ->assertOk()
            ->assertJsonCount(2, 'grades')
            ->assertJsonPath('grades.0.name', 'Grade 1')
            ->assertJsonPath('grades.0.services_count', 0);
    }

    public function test_name_and_level_must_be_unique(): void
    {
        $this->admin();
        Grade::create(['name' => 'Grade 8', 'level' => 8, 'is_active' => true]);

        $this->postJson('/api/admin/grades', ['name' => 'Grade 8', 'level' => 8])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'level']);
    }

    public function test_admin_can_edit_a_grade_keeping_its_own_name_and_level(): void
    {
        $this->admin();
        $grade = Grade::create(['name' => 'Grade 8', 'level' => 8, 'is_active' => true]);

        $this->patchJson("/api/admin/grades/{$grade->id}", ['name' => 'Grade 8', 'level' => 8, 'description' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('grade.description', 'Updated');
    }

    public function test_deactivating_a_grade_hides_it_from_the_public_list(): void
    {
        $this->admin();
        $grade = Grade::create(['name' => 'Grade 8', 'level' => 8, 'is_active' => true]);

        $this->postJson("/api/admin/grades/{$grade->id}/deactivate")->assertOk()->assertJsonPath('grade.is_active', false);
        $this->getJson('/api/grades')->assertOk()->assertJsonCount(0, 'grades');

        $this->postJson("/api/admin/grades/{$grade->id}/activate")->assertOk()->assertJsonPath('grade.is_active', true);
        $this->getJson('/api/grades')->assertOk()->assertJsonCount(1, 'grades');
    }

    public function test_non_admins_cannot_manage_grades(): void
    {
        Sanctum::actingAs(User::factory()->tutor()->create());

        $this->postJson('/api/admin/grades', ['name' => 'Grade 8', 'level' => 8])->assertForbidden();
        $this->assertSame(0, Grade::count());
    }
}
