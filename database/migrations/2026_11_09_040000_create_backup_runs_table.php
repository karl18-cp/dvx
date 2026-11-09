<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('running')->index();
            $table->string('trigger', 24)->default('scheduled');
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('encrypted_size')->nullable();
            $table->string('encrypted_checksum', 64)->nullable();
            $table->json('contents')->nullable();
            $table->unsignedInteger('database_tables')->default(0);
            $table->unsignedBigInteger('database_rows')->default(0);
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedBigInteger('source_bytes')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('restore_tested_at')->nullable();
            $table->string('restore_test_status', 24)->nullable();
            $table->text('restore_test_message')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();
            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
