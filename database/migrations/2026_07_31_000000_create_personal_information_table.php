<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('birth_date');
            $table->date('start_date');
            $table->string('gender', 50);
            $table->string('civil_status', 50);
            $table->string('phone', 30);
            $table->text('address');
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_relationship', 100);
            $table->string('emergency_contact_phone', 30);
            $table->text('emergency_contact_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_information');
    }
};
