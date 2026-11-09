<?php

namespace Tests\Feature;

use App\Models\RolePermission;
use App\Models\User;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_and_reset_a_role_permission_matrix(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active']);
        $service = app(RolePermissionService::class);
        $matrix = $service->matrix()['manager'];
        $this->assertFalse($service->can($manager, 'sanctions.manage'));

        $matrix['sanctions.manage'] = true;
        $this->actingAs($admin)
            ->put('/role-permissions/manager', ['permissions' => $matrix])
            ->assertSessionHasNoErrors();

        $this->assertTrue($service->can($manager->fresh(), 'sanctions.manage'));
        $this->actingAs($manager)->get('/sanctions')->assertOk();
        $this->assertDatabaseHas('role_permissions', [
            'role' => 'manager',
            'permission' => 'sanctions.manage',
            'allowed' => true,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'Role permissions updated',
            'actor_id' => $admin->id,
        ]);

        $this->actingAs($admin)->delete('/role-permissions/manager')->assertSessionHasNoErrors();
        $this->assertFalse($service->can($manager->fresh(), 'sanctions.manage'));
        $this->assertSame(0, RolePermission::where('role', 'manager')->count());
    }

    public function test_permissions_control_salary_reports_and_attendance_editing(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $service = app(RolePermissionService::class);
        $agentMatrix = $service->matrix()['agent'];

        $this->actingAs($agent)->get('/accounting')->assertForbidden();
        $agentMatrix['salaries.view'] = true;
        $agentMatrix['reports.export'] = true;
        $this->actingAs($admin)->put('/role-permissions/agent', ['permissions' => $agentMatrix])->assertSessionHasNoErrors();
        $this->actingAs($agent)->get('/accounting')->assertOk();
        $this->getJson('/accounting/government-reports?year=2026')->assertOk();

        $accounting = User::factory()->create(['role' => 'accounting', 'status' => 'active']);
        $accountingMatrix = $service->matrix()['accounting'];
        $this->actingAs($accounting)->get('/attendance')->assertOk();
        $accountingMatrix['attendance.view'] = false;
        $accountingMatrix['attendance.edit'] = false;
        $this->actingAs($admin)->put('/role-permissions/accounting', ['permissions' => $accountingMatrix])->assertSessionHasNoErrors();
        $this->actingAs($accounting)->get('/attendance')->assertForbidden();
    }

    public function test_permission_manager_itself_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager', 'status' => 'active']))
            ->get('/role-permissions')->assertForbidden();
    }
}
