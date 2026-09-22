<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->timestamp('publish_at')->nullable()->after('published_at')->index();
        });

        Schema::create('assessment_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('assessment_assignments')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title');
            $table->text('message');
            $table->string('action_url', 1024)->nullable();
            $table->string('deduplication_key')->unique();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['recipient_id', 'read_at', 'created_at'], 'assessment_notification_recipient_index');
            $table->index(['assignment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_notifications');
        Schema::table('assessments', fn (Blueprint $table) => $table->dropColumn('publish_at'));
    }
};
