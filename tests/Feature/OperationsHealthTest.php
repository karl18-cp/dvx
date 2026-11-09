<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\OperationsHealthCheck;
use App\Models\User;
use App\Services\OperationsHealthService;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['cache.default' => 'array']);
    }

    public function test_health_snapshot_reports_all_required_services(): void
    {
        $service = app(OperationsHealthService::class);
        $service->heartbeat();
        BackupRun::create([
            'status' => 'completed',
            'trigger' => 'scheduled',
            'file_name' => 'system-backups/test.dvxbak',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'restore_tested_at' => now(),
            'restore_test_status' => 'passed',
        ]);
        Storage::disk('local')->put('profile-photos/test.jpg', 'photo');

        $snapshot = $service->snapshot();

        $this->assertSame(
            ['email', 'scheduler', 'database', 'storage', 'failedJobs', 'backup', 'uptime'],
            array_keys($snapshot['services']),
        );
        $this->assertSame('healthy', $snapshot['services']['scheduler']['status']);
        $this->assertSame('healthy', $snapshot['services']['database']['status']);
        $this->assertSame('healthy', $snapshot['services']['failedJobs']['status']);
        $this->assertSame('healthy', $snapshot['services']['backup']['status']);
        $this->assertGreaterThanOrEqual(6, $snapshot['summary']['healthy']);
        $this->assertSame([], $snapshot['failed_job_items']);
    }

    public function test_stale_scheduler_and_failed_job_are_critical(): void
    {
        OperationsHealthCheck::create([
            'service_key' => 'scheduler',
            'status' => 'healthy',
            'details' => ['healthy_since' => now()->subHour()->toISOString()],
            'checked_at' => now()->subMinutes(10),
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ExampleJob']),
            'exception' => 'RuntimeException: simulated test failure',
            'failed_at' => now(),
        ]);

        $snapshot = app(OperationsHealthService::class)->snapshot();

        $this->assertSame('critical', $snapshot['services']['scheduler']['status']);
        $this->assertSame('critical', $snapshot['services']['failedJobs']['status']);
        $this->assertSame('critical', $snapshot['services']['uptime']['status']);
        $this->assertSame('ExampleJob', class_basename($snapshot['failed_job_items'][0]['job']));
        $this->assertSame('RuntimeException', $snapshot['failed_job_items'][0]['error_type']);
    }

    public function test_operations_dashboard_is_permission_controlled(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $itAdmin = User::factory()->create(['role' => 'it_admin', 'status' => 'active']);

        $this->actingAs($agent)->get('/operations-health')->assertForbidden();
        $this->assertFalse(app(RolePermissionService::class)->can($agent, 'operations.view'));
        $this->assertTrue(app(RolePermissionService::class)->can($itAdmin, 'operations.view'));
    }

    public function test_heartbeat_preserves_uptime_until_a_gap_occurs(): void
    {
        $service = app(OperationsHealthService::class);
        $first = $service->heartbeat();
        $healthySince = $first->details['healthy_since'];
        $second = $service->heartbeat();
        $this->assertSame($healthySince, $second->details['healthy_since']);

        $second->update(['checked_at' => now()->subMinutes(5)]);
        $afterGap = $service->heartbeat();
        $this->assertNotSame($healthySince, $afterGap->details['healthy_since']);
    }
}
