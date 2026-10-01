<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('contract_type', 30)->index();
            $table->string('title', 180);
            $table->string('reference_number', 100)->nullable();
            $table->date('effective_date');
            $table->date('end_date')->nullable()->index();
            $table->string('status', 25)->index();
            $table->text('notes')->nullable();
            $table->string('file_disk', 30)->default('local');
            $table->string('file_path');
            $table->string('original_filename', 200);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('archive_reason')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'contract_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};
