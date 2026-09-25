<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('employee_sanctions', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('sanction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sanction_name', 150);
            $table->text('sanction_description')->nullable();
            $table->string('employee_name');
            $table->string('employee_username')->nullable();
            $table->string('employee_role');
            $table->string('issuer_name');
            $table->string('punishment', 100);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'created_at']);
            $table->index(['sanction_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_sanctions');
        Schema::dropIfExists('sanctions');
    }
};
