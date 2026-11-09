<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_documents', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 20)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type', 40)->index();
            $table->string('title');
            $table->string('issuer')->nullable();
            $table->string('document_number', 120)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->index();
            $table->unsignedSmallInteger('remind_days_before')->default(30);
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->string('file_disk', 30)->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'document_type']);
            $table->index(['job_application_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_documents');
    }
};
