<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_admin_can_view_and_filter_the_central_audit_trail(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active', 'username' => 'DVX900']);
        app(AuditLogService::class)->record($admin, 'attendance', 'Attendance time corrected', $employee, metadata: ['date' => '2026-11-09'], subjectId: $employee->id);

        $this->actingAs($admin)->get('/audit-trail?category=attendance&search=DVX900')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('audit-trail')->has('logs.data', 1)->where('logs.data.0.action', 'Attendance time corrected'));

        $this->actingAs($employee)->get('/audit-trail')->assertForbidden();
    }

    public function test_audit_metadata_is_sanitized_and_records_are_immutable(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $log = app(AuditLogService::class)->record($admin, 'banking', 'Bank information viewed', $employee, metadata: ['account_number' => '123456789', 'nested' => ['api_token' => 'secret']], subjectId: $employee->id);

        $this->assertSame('[REDACTED]', $log->metadata['account_number']);
        $this->assertSame('[REDACTED]', $log->metadata['nested']['api_token']);
        $this->expectException(\LogicException::class);
        $log->delete();
    }
}
