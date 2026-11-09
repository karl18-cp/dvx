<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\User;
use App\Services\BackupService;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config([
            'backups.disk' => 'local',
            'backups.encryption_key' => str_repeat('backup-test-key-', 4),
            'backups.retention_days' => 30,
        ]);
    }

    public function test_admin_can_create_download_and_restore_test_an_encrypted_backup(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Storage::disk('local')->put('profile-photos/person.jpg', 'private-profile-photo-marker');
        Storage::disk('local')->put('job-applications/2026/11/resume.pdf', 'private-resume-marker');
        Storage::disk('local')->put('employee-contracts/1/contract.pdf', 'private-contract-marker');
        Storage::disk('local')->put('call-evaluations/1/call.mp3', 'private-recording-marker');
        Storage::disk('local')->put('accounting-documents/invoice.pdf', 'private-accounting-marker');

        $run = app(BackupService::class)->run($admin, 'manual');

        $this->assertSame('completed', $run->status);
        $this->assertSame(5, $run->file_count);
        $this->assertGreaterThan(0, $run->database_tables);
        $this->assertGreaterThan(0, $run->database_rows);
        Storage::disk('local')->assertExists($run->file_name);
        $encrypted = Storage::disk('local')->get($run->file_name);
        $this->assertStringStartsWith("DVXBACKUP1\n", $encrypted);
        $this->assertStringNotContainsString('private-profile-photo-marker', $encrypted);
        $this->assertStringNotContainsString('private-resume-marker', $encrypted);

        $result = app(BackupService::class)->testRestore($run);
        $this->assertSame(6, $result['entries']);
        $this->assertGreaterThan(0, $result['database_rows']);
        $this->assertSame('passed', $run->fresh()->restore_test_status);

        $this->actingAs($admin)->get("/backup-recovery/{$run->id}/download")->assertOk();
    }

    public function test_restore_test_detects_tampering_and_alerts_administrators(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $run = app(BackupService::class)->run($admin, 'manual');
        $contents = Storage::disk('local')->get($run->file_name);
        $contents[strlen($contents) - 8] = chr(ord($contents[strlen($contents) - 8]) ^ 1);
        Storage::disk('local')->put($run->file_name, $contents);

        $this->actingAs($admin)
            ->post("/backup-recovery/{$run->id}/test")
            ->assertSessionHasErrors('restore');

        $this->assertSame('failed', $run->fresh()->restore_test_status);
        $this->assertDatabaseHas('assessment_notifications', [
            'recipient_id' => $admin->id,
            'type' => 'backup_failure',
            'title' => 'Backup restore test failed',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $admin->id,
            'action' => 'Isolated backup restore test failed',
        ]);
    }

    public function test_backup_dashboard_is_permission_controlled(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $itAdmin = User::factory()->create(['role' => 'it_admin', 'status' => 'active']);

        $this->actingAs($agent)->get('/backup-recovery')->assertForbidden();
        $this->assertTrue(app(RolePermissionService::class)->can($itAdmin, 'backups.manage'));
    }

    public function test_old_backup_files_are_expired_by_retention_policy(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $run = app(BackupService::class)->run($admin, 'manual');
        $path = $run->file_name;
        $run->update(['completed_at' => now()->subDays(31)]);

        app(BackupService::class)->prune();

        Storage::disk('local')->assertMissing($path);
        $this->assertSame('expired', $run->fresh()->status);
        $this->assertNull($run->fresh()->file_name);
    }
}
