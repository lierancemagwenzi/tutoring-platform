<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminRouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function adminRoutes(): array
    {
        return [
            ['GET', '/api/admin/dashboard'],
            ['GET', '/api/admin/subjects'],
            ['GET', '/api/admin/tutors/pending'],
            ['GET', '/api/admin/tutor-subject-requests'],
            ['GET', '/api/admin/settings'],
            ['GET', '/api/admin/quick-setup'],
            ['GET', '/api/admin/integrations'],
            ['GET', '/api/admin/system-health'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_student_is_forbidden_from_every_admin_route(string $method, string $uri): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->json($method, $uri);

        $response->assertForbidden();
    }

    #[DataProvider('adminRoutes')]
    public function test_tutor_is_forbidden_from_every_admin_route(string $method, string $uri): void
    {
        $tutor = User::factory()->tutor()->create();
        Sanctum::actingAs($tutor);

        $response = $this->json($method, $uri);

        $response->assertForbidden();
    }

    public function test_guest_is_unauthorized_on_admin_routes(): void
    {
        $response = $this->getJson('/api/admin/dashboard');

        $response->assertUnauthorized();
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertOk();
    }
}
