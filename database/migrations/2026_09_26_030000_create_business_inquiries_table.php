<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('business_inquiries', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_id')->unique();
            $table->string('name', 150);
            $table->string('company', 200);
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->string('service', 100);
            $table->text('message');
            $table->timestamp('meeting_at');
            $table->string('timezone', 100);
            $table->string('status')->default('new')->index();
            $table->text('admin_notes')->nullable();
            $table->string('email_status')->default('pending');
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('business_inquiries'); }
};
