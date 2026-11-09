<?php

namespace App\Http\Controllers;

use App\Models\BackupRun;
use App\Services\AuditLogService;
use App\Services\BackupService;
use App\Services\RolePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupRecoveryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize($request);
        $latest = BackupRun::query()->latest('id')->first();
        $lastPassed = BackupRun::query()->where('restore_test_status', 'passed')->latest('restore_tested_at')->first();

        return Inertia::render('backup-recovery', [
            'backups' => BackupRun::query()->with('requester:id,name,username')->latest('id')->paginate(20)->through(fn (BackupRun $run) => [
                'id' => $run->id,
                'status' => $run->status,
                'trigger' => $run->trigger,
                'requester' => $run->requester?->only(['id', 'name', 'username']),
                'encrypted_size' => $run->encrypted_size,
                'encrypted_checksum' => $run->encrypted_checksum,
                'contents' => $run->contents,
                'database_tables' => $run->database_tables,
                'database_rows' => $run->database_rows,
                'file_count' => $run->file_count,
                'source_bytes' => $run->source_bytes,
                'started_at' => $run->started_at?->toISOString(),
                'completed_at' => $run->completed_at?->toISOString(),
                'restore_tested_at' => $run->restore_tested_at?->toISOString(),
                'restore_test_status' => $run->restore_test_status,
                'restore_test_message' => $run->restore_test_message,
                'failure_message' => $run->failure_message,
                'downloadable' => $run->status === 'completed' && (bool) $run->file_name,
            ]),
            'health' => [
                'latest_status' => $latest?->status ?? 'never',
                'latest_completed_at' => $latest?->completed_at?->toISOString(),
                'last_restore_test_at' => $lastPassed?->restore_tested_at?->toISOString(),
                'retention_days' => (int) config('backups.retention_days'),
                'schedule' => config('backups.schedule'),
                'timezone' => config('backups.timezone'),
                'encryption' => 'AES-256-GCM authenticated chunks',
            ],
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function run(Request $request, BackupService $backups, AuditLogService $audit)
    {
        $this->authorize($request);
        try {
            $run = $backups->run($request->user(), 'manual');
            $audit->record($request->user(), 'backups', 'Encrypted system backup requested', $run, metadata: ['status' => $run->status, 'database_rows' => $run->database_rows, 'file_count' => $run->file_count]);

            return back()->with('status', $run->status === 'completed' ? 'Encrypted backup completed successfully.' : 'Backup failed. Review the failure details below.');
        } catch (Throwable $exception) {
            return back()->withErrors(['backup' => $exception->getMessage()]);
        }
    }

    public function test(Request $request, BackupRun $backupRun, BackupService $backups, AuditLogService $audit)
    {
        $this->authorize($request);
        try {
            $result = $backups->testRestore($backupRun);
            $audit->record($request->user(), 'backups', 'Isolated backup restore test passed', $backupRun, metadata: $result);

            return back()->with('status', 'Restore test passed. Encryption, database rows, files, and checksums were verified.');
        } catch (Throwable $exception) {
            $audit->record($request->user(), 'backups', 'Isolated backup restore test failed', $backupRun, metadata: ['message' => $exception->getMessage()]);

            return back()->withErrors(['restore' => $exception->getMessage()]);
        }
    }

    public function download(Request $request, BackupRun $backupRun): StreamedResponse
    {
        $this->authorize($request);
        abort_unless($backupRun->status === 'completed' && $backupRun->file_name && Storage::disk(config('backups.disk'))->exists($backupRun->file_name), 404);
        $name = 'divertex-backup-'.$backupRun->id.'-'.$backupRun->completed_at?->format('Ymd-His').'.dvxbak';

        return Storage::disk(config('backups.disk'))->download($backupRun->file_name, $name, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorize(Request $request): void
    {
        abort_unless(app(RolePermissionService::class)->can($request->user(), 'backups.manage'), 403);
    }
}
