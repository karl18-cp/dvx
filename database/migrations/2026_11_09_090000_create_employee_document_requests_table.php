<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_document_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 40)->index();
            $table->string('other_document_type', 160)->nullable();
            $table->text('purpose');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('delivery_method', 20)->default('portal');
            $table->string('status', 24)->default('submitted')->index();
            $table->text('employee_notes')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->string('file_disk', 30)->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename', 200)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->string('email_error', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_document_requests');
    }
};
