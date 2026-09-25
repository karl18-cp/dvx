<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_form_settings', function (Blueprint $table) {
            $table->id();
            $table->string('kind')->unique();
            $table->json('definition');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        foreach (['job_applications', 'business_inquiries'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->json('custom_answers')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['job_applications', 'business_inquiries'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('custom_answers'));
        }
        Schema::dropIfExists('public_form_settings');
    }
};
