<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 40)->index();
            $table->string('action', 120);
            $table->string('target_type', 160);
            $table->unsignedBigInteger('target_id');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('source', 40)->default('application');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id', 'created_at']);
            $table->index(['subject_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->unique(['source', 'source_id']);
        });

        if (! Schema::hasTable('assessment_activity_logs')) {
            return;
        }

        DB::table('assessment_activity_logs')->orderBy('id')->chunkById(500, function ($rows): void {
            $records = $rows->map(function ($row): array {
                $metadata = $row->metadata ? json_decode($row->metadata, true) : null;
                $action = mb_strtolower($row->action);
                $category = match (true) {
                    str_contains($action, 'attendance') => 'attendance',
                    str_contains($action, 'bank') => 'banking',
                    str_contains($action, 'accounting'), str_contains($action, 'expense'), str_contains($action, 'payment'), str_contains($action, 'payroll'), str_contains($action, 'payslip'), str_contains($action, 'backpay'), str_contains($action, '13th-month') => 'payroll',
                    str_contains($action, 'schedule'), str_contains($action, 'team'), str_contains($action, 'campaign') => 'scheduling',
                    str_contains($action, 'status'), str_contains($action, 'role'), str_contains($action, 'account') => 'account_status',
                    str_contains($action, 'sanction') => 'sanctions',
                    str_contains($action, 'leave'), str_contains($action, 'overtime'), str_contains($action, 'undertime'), str_contains($action, 'approval') => 'approvals',
                    str_contains($action, 'contract') => 'contracts',
                    str_contains($action, 'assessment') => 'assessments',
                    str_contains($action, 'coaching'), str_contains($action, 'training') => 'training',
                    str_contains($action, 'evaluation'), str_contains($action, 'scorecard'), str_contains($action, 'qa ') => 'quality',
                    default => 'general',
                };
                $subject = str_ends_with($row->target_type, '\\User') ? $row->target_id : ($metadata['employee_id'] ?? $metadata['user_id'] ?? null);

                return [
                    'actor_id' => $row->actor_id,
                    'subject_id' => is_numeric($subject) ? (int) $subject : null,
                    'category' => $category,
                    'action' => $row->action,
                    'target_type' => $row->target_type,
                    'target_id' => $row->target_id,
                    'metadata' => $row->metadata,
                    'source' => 'legacy_assessment_log',
                    'source_id' => $row->id,
                    'created_at' => $row->created_at,
                ];
            })->all();

            DB::table('activity_logs')->insert($records);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
