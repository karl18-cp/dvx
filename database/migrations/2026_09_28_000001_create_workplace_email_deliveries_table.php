<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workplace_email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 64)->unique();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('notification_id')->nullable()->constrained('assessment_notifications')->nullOnDelete();
            $table->string('kind', 30);
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workplace_email_deliveries');
    }
};
