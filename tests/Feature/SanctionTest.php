<?php

namespace Tests\Feature;

use App\Models\EmployeeSanction;
use App\Models\Sanction;
use App\Models\User;
use Database\Seeders\SanctionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SanctionTest extends TestCase
{
    use RefreshDatabase;

    private function issueData(Sanction $sanction, User $employee): array
    {
        return ['request_id' => (string) Str::uuid(), 'sanction_id' => $sanction->id, 'employee_id' => $employee->id, 'punishment' => 'Documented Verbal', 'notes' => 'Reviewed with employee.'];
    }

    public function test_catalog_starts_with_the_screenshot_options_and_can_be_managed(): void
    {
        $this->seed(SanctionCatalogSeeder::class);
        $this->seed(SanctionCatalogSeeder::class);
        $this->assertDatabaseCount('sanctions', 10);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/sanctions')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('sanctions')->has('sanctions', 10)->where('punishments', EmployeeSanction::PUNISHMENTS)->where('records.total', 0));
        $this->post('/sanctions', ['name' => '  Custom sanction  ', 'description' => 'Details'])->assertSessionHasNoErrors();
        $sanction = Sanction::query()->where('name', 'Custom sanction')->sole();
        $this->put("/sanctions/$sanction->id", ['name' => 'Updated sanction', 'description' => 'Revised'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sanctions', ['id' => $sanction->id, 'name' => 'Updated sanction']);
        $this->delete("/sanctions/$sanction->id")->assertRedirect('/sanctions');
        $this->assertDatabaseMissing('sanctions', ['id' => $sanction->id]);
    }

    public function test_admin_can_issue_to_every_employee_role_without_changing_login_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sanction = Sanction::query()->create(['name' => 'Tardiness', 'description' => 'Late arrival']);
        foreach (['agent', 'team_leader', 'manager', 'it_admin', 'it_support', 'it_developer', 'admin'] as $role) {
            $employee = User::factory()->create(['role' => $role, 'status' => 'active']);
            $data = [...$this->issueData($sanction, $employee), 'punishment' => 'Suspension'];
            $this->actingAs($admin)->post('/sanctions/issue', $data)->assertRedirect('/sanctions')->assertSessionHasNoErrors();
            $this->assertDatabaseHas('employee_sanctions', ['employee_id' => $employee->id, 'issued_by' => $admin->id, 'employee_role' => $role, 'sanction_name' => 'Tardiness', 'punishment' => 'Suspension']);
            $this->assertSame('active', $employee->fresh()->status);
        }
        $this->assertDatabaseCount('employee_sanctions', 7);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Sanction Issued']);
    }

    public function test_retries_do_not_duplicate_records_and_reused_submission_ids_cannot_change_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent']);
        $sanction = Sanction::query()->create(['name' => 'Tardiness']);
        $data = $this->issueData($sanction, $employee);
        $this->actingAs($admin)->post('/sanctions/issue', $data)->assertSessionHasNoErrors();
        $this->post('/sanctions/issue', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('employee_sanctions', 1);
        $this->assertDatabaseCount('assessment_activity_logs', 1);
        $this->post('/sanctions/issue', [...$data, 'punishment' => 'Final Written'])->assertSessionHasErrors('request_id');
        $this->assertDatabaseCount('employee_sanctions', 1);
        $this->assertSame('Documented Verbal', EmployeeSanction::query()->sole()->punishment);
    }

    public function test_issued_details_survive_catalog_edits_and_deletion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'agent', 'name' => 'Original Employee']);
        $sanction = Sanction::query()->create(['name' => 'Original sanction', 'description' => 'Original description']);
        $this->actingAs($admin)->post('/sanctions/issue', $this->issueData($sanction, $employee))->assertSessionHasNoErrors();
        $this->put("/sanctions/$sanction->id", ['name' => 'Changed', 'description' => 'Changed'])->assertSessionHasNoErrors();
        $employee->update(['name' => 'Renamed Employee', 'role' => 'team_leader']);
        $this->delete("/sanctions/$sanction->id")->assertSessionHasNoErrors();
        $record = EmployeeSanction::query()->sole();
        $this->assertNull($record->sanction_id);
        $this->assertSame('Original sanction', $record->sanction_name);
        $this->assertSame('Original description', $record->sanction_description);
        $this->assertSame('Original Employee', $record->employee_name);
        $this->assertSame('agent', $record->employee_role);
        $this->get('/sanctions')->assertInertia(fn (Assert $page) => $page->where('records.total', 1)->where('records.data.0.sanction_name', 'Original sanction'));
    }

    public function test_history_search_and_punishment_filters_are_paginated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['name' => 'Search Employee', 'username' => 'SAN001']);
        $other = User::factory()->create(['name' => 'Other Employee']);
        $sanction = Sanction::query()->create(['name' => 'Tardiness']);
        $this->actingAs($admin);
        foreach (range(1, 16) as $number) {
            $this->post('/sanctions/issue', $this->issueData($sanction, $employee))->assertSessionHasNoErrors();
        }
        $this->post('/sanctions/issue', [...$this->issueData($sanction, $other), 'punishment' => 'First Written']);
        $this->get('/sanctions?search=SAN001&punishment=Documented%20Verbal')->assertInertia(fn (Assert $page) => $page->where('records.total', 16)->has('records.data', 15));
        $this->get('/sanctions?search=SAN001&punishment=Documented%20Verbal&page=2')->assertInertia(fn (Assert $page) => $page->where('records.total', 16)->has('records.data', 1));
        $this->get('/sanctions?punishment=First%20Written')->assertInertia(fn (Assert $page) => $page->where('records.total', 1));
    }

    public function test_catalog_and_issue_validation_prevent_invalid_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sanction = Sanction::query()->create(['name' => 'Tardiness']);
        $this->actingAs($admin)->post('/sanctions', ['name' => 'Tardiness'])->assertSessionHasErrors('name');
        $this->post('/sanctions', ['name' => '   '])->assertSessionHasErrors('name');
        $this->post('/sanctions/issue', [
            'request_id' => 'bad', 'sanction_id' => 99999, 'employee_id' => 99999, 'punishment' => 'Invalid punishment',
        ])->assertSessionHasErrors(['request_id', 'sanction_id', 'employee_id', 'punishment']);
        $this->post('/sanctions/issue', [...$this->issueData($sanction, $admin), 'notes' => str_repeat('x', 5001)])->assertSessionHasErrors('notes');
        $this->assertDatabaseCount('employee_sanctions', 0);
    }

    public function test_non_admins_cannot_read_or_modify_sanctions(): void
    {
        $sanction = Sanction::query()->create(['name' => 'Tardiness']);
        $this->get('/sanctions')->assertRedirect('/login');
        foreach (['agent', 'team_leader', 'manager', 'it_admin', 'it_support', 'it_developer'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/sanctions')->assertForbidden();
            $this->post('/sanctions', ['name' => 'Unauthorized'])->assertForbidden();
            $this->put("/sanctions/$sanction->id", ['name' => 'Unauthorized'])->assertForbidden();
            $this->delete("/sanctions/$sanction->id")->assertForbidden();
            $this->post('/sanctions/issue', $this->issueData($sanction, $user))->assertForbidden();
        }
        $this->assertDatabaseCount('sanctions', 1);
        $this->assertDatabaseCount('employee_sanctions', 0);
    }
}
