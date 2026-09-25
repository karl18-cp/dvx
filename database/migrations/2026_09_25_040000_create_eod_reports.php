<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eod_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name');
            $table->string('author_username')->nullable();
            $table->string('author_role', 30);
            $table->date('report_date');
            $table->text('summary');
            $table->text('blockers')->nullable();
            $table->text('next_steps')->nullable();
            $table->json('task_snapshots');
            $table->unsignedSmallInteger('task_count');
            $table->timestamps();
            $table->index(['user_id', 'report_date']);
            $table->index(['report_date', 'author_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eod_reports');
    }
};
