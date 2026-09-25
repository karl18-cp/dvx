<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satisfaction_ratings', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_name');
            $table->string('employee_username')->nullable();
            $table->string('employee_role');
            $table->string('reviewer_name');
            $table->string('reviewer_username')->nullable();
            $table->json('teams');
            foreach (['quality', 'productivity', 'attendance', 'communication', 'professionalism'] as $category) {
                $table->unsignedTinyInteger($category);
            }
            $table->decimal('average', 3, 2);
            $table->text('comments')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_ratings');
    }
};
