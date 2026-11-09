<?php

namespace App\Services;

use App\Models\BackupRun;
use App\Models\OperationsHealthCheck;
use App\Models\WorkplaceEmailDelivery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OperationsHealthService
{
    public function heartbeat(): OperationsHealthCheck
    {
        $now = now();
        $check = OperationsHealthCheck::query()->where('service_key', 'scheduler')->first();
        $previous = $check?->checked_at;
        $healthySince = data_get($check?->details, 'healthy_since');
        if (! $previous || $previous->lt($now->copy()->subMinutes(3)) || ! $healthySince) {
            $healthySince = $now->toISOString();
        }

        return OperationsHealthCheck::query()->updateOrCreate(
            ['service_key' => 'scheduler'],
            ['status' => 'healthy', 'checked_at' => $now, 'details' => ['healthy_since' => $healthySince, 'command' => 'schedule:run']],
        );
    }

    public function snapshot(): array
    {
        $database = $this->databaseHealth();
        $scheduler = $this->schedulerHealth();
        $email = $this->emailHealth();
        $failedJobs = $this->failedJobsHealth();
        $storage = $this->storageHealth();
        $backup = $this->backupHealth();
        $uptime = $this->uptimeHealth($scheduler);
        $services = compact('email', 'scheduler', 'database', 'storage', 'failedJobs', 'backup', 'uptime');

        return [
            'services' => $services,
            'summary' => [
                'healthy' => collect($services)->where('status', 'healthy')->count(),
                'warning' => collect($services)->where('status', 'warning')->count(),
                'critical' => collect($services)->where('status', 'critical')->count(),
                'checked_at' => now()->toISOString(),
            ],
            'failed_job_items' => $this->recentFailedJobs(),
        ];
    }

    private function databaseHealth(): array
    {
        $started = hrtime(true);
        try {
            DB::select('select 1');
            $latency = round((hrtime(true) - $started) / 1_000_000, 1);

            return $this->service(
                $latency >= 1000 ? 'critical' : ($latency >= 250 ? 'warning' : 'healthy'),
                'Database',
                $latency < 250 ? 'Connected normally' : 'Connected, but response is slower than expected',
                ['latency_ms' => $latency, 'driver' => DB::getDriverName(), 'database' => DB::connection()->getDatabaseName()],
            );
        } catch (Throwable $exception) {
            return $this->service('critical', 'Database', 'Database connection failed', ['error' => class_basename($exception)]);
        }
    }

    private function schedulerHealth(): array
    {
        $check = OperationsHealthCheck::query()->where('service_key', 'scheduler')->first();
        $age = $check?->checked_at?->diffInSeconds(now());
        $status = $age === null || $age > 300 ? 'critical' : ($age > 150 ? 'warning' : 'healthy');

        return $this->service(
            $status,
            'Scheduled jobs',
            $age === null ? 'No scheduler heartbeat has been recorded' : ($status === 'healthy' ? 'Scheduler heartbeat is current' : 'Scheduler heartbeat is late'),
            ['last_heartbeat_at' => $check?->checked_at?->toISOString(), 'age_seconds' => $age, 'healthy_since' => data_get($check?->details, 'healthy_since')],
        );
    }

    private function emailHealth(): array
    {
        $pending = WorkplaceEmailDelivery::query()->whereIn('status', ['pending', 'processing'])->count();
        $failed = WorkplaceEmailDelivery::query()->where('status', 'failed')->count();
        $oldest = WorkplaceEmailDelivery::query()->whereIn('status', ['pending', 'processing'])->oldest('created_at')->value('created_at');
        $oldestAge = $oldest ? Carbon::parse($oldest)->diffInMinutes(now()) : 0;
        $queuedJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $status = $failed > 0 || $oldestAge > 30 ? 'critical' : (($pending > 0 && $oldestAge > 10) || $queuedJobs > 25 ? 'warning' : 'healthy');

        return $this->service(
            $status,
            'Email queue',
            $status === 'healthy' ? 'Email deliveries are processing normally' : ($failed ? 'Some email deliveries have failed' : 'Email deliveries are waiting longer than expected'),
            [
                'pending_deliveries' => $pending,
                'failed_deliveries' => $failed,
                'oldest_pending_minutes' => $oldestAge,
                'queued_jobs' => $queuedJobs,
                'last_sent_at' => WorkplaceEmailDelivery::query()->where('status', 'sent')->latest('sent_at')->value('sent_at'),
            ],
        );
    }

    private function failedJobsHealth(): array
    {
        $count = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

        return $this->service(
            $count > 0 ? 'critical' : 'healthy',
            'Failed jobs',
            $count > 0 ? "{$count} queue job(s) require attention" : 'No failed queue jobs',
            ['count' => $count, 'latest_failed_at' => $count ? DB::table('failed_jobs')->max('failed_at') : null],
        );
    }

    private function storageHealth(): array
    {
        return Cache::remember('operations-health:storage', now()->addMinutes(5), function (): array {
            $root = Storage::disk('local')->path('');
            $total = @disk_total_space($root) ?: 0;
            $free = @disk_free_space($root) ?: 0;
            $used = max(0, $total - $free);
            $percent = $total > 0 ? round(($used / $total) * 100, 1) : null;
            $privateBytes = $this->directoryBytes($root);
            $status = $percent === null ? 'warning' : ($percent >= 90 ? 'critical' : ($percent >= 80 ? 'warning' : 'healthy'));

            return $this->service(
                $status,
                'Storage usage',
                $percent === null ? 'Disk capacity could not be measured' : ($status === 'healthy' ? 'Storage capacity is within the safe range' : 'Available disk capacity is running low'),
                ['total_bytes' => $total, 'used_bytes' => $used, 'free_bytes' => $free, 'used_percent' => $percent, 'private_files_bytes' => $privateBytes],
            );
        });
    }

    private function backupHealth(): array
    {
        $latest = BackupRun::query()->latest('id')->first();
        $latestCompleted = BackupRun::query()->where('status', 'completed')->latest('completed_at')->first();
        $age = $latestCompleted?->completed_at?->diffInHours(now());
        $status = ! $latestCompleted || $age > 36 || $latest?->status === 'failed'
            ? 'critical'
            : ($age > 26 || $latestCompleted->restore_test_status !== 'passed' ? 'warning' : 'healthy');

        return $this->service(
            $status,
            'Last backup',
            ! $latestCompleted ? 'No completed backup exists' : ($latest?->status === 'failed' ? 'The latest backup attempt failed' : ($status === 'healthy' ? 'Recent backup and restore test are healthy' : 'Backup exists but is stale or not restore-tested')),
            [
                'backup_id' => $latestCompleted?->id,
                'latest_status' => $latest?->status,
                'completed_at' => $latestCompleted?->completed_at?->toISOString(),
                'age_hours' => $age,
                'restore_test_status' => $latestCompleted?->restore_test_status,
                'restore_tested_at' => $latestCompleted?->restore_tested_at?->toISOString(),
            ],
        );
    }

    private function uptimeHealth(array $scheduler): array
    {
        $healthySince = data_get($scheduler, 'metrics.healthy_since');
        $seconds = $healthySince ? Carbon::parse($healthySince)->diffInSeconds(now()) : 0;
        $status = $scheduler['status'] === 'critical' ? 'critical' : ($scheduler['status'] === 'warning' ? 'warning' : 'healthy');

        return $this->service(
            $status,
            'Application uptime',
            $healthySince ? 'Continuous scheduler-observed application availability' : 'Uptime monitoring is waiting for its first heartbeat',
            ['healthy_since' => $healthySince, 'uptime_seconds' => $seconds, 'php_version' => PHP_VERSION, 'environment' => app()->environment()],
        );
    }

    private function recentFailedJobs(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [];
        }

        return DB::table('failed_jobs')->latest('failed_at')->limit(10)->get()->map(function ($job): array {
            $payload = json_decode($job->payload, true);

            return [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'job' => data_get($payload, 'displayName', 'Unknown job'),
                'failed_at' => Carbon::parse($job->failed_at)->toISOString(),
                'error_type' => str((string) $job->exception)->before(':')->afterLast('\\')->limit(100)->toString(),
            ];
        })->all();
    }

    private function directoryBytes(string $directory): int
    {
        if (! is_dir($directory)) {
            return 0;
        }
        $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && ! $file->isLink()) {
                $bytes += $file->getSize();
            }
        }

        return $bytes;
    }

    private function service(string $status, string $label, string $message, array $metrics): array
    {
        return compact('status', 'label', 'message', 'metrics');
    }
}
