<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_government_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('tin', 30)->nullable();
            $table->string('rdo_code', 10)->nullable();
            $table->string('sss_number', 30)->nullable();
            $table->string('philhealth_number', 30)->nullable();
            $table->string('pagibig_number', 30)->nullable();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_government_details');
    }
};
