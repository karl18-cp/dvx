<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('training_campaign_id')->nullable()->constrained('campaigns')->restrictOnDelete();
            $table->string('training_status')->nullable()->index();
            $table->foreignId('training_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('training_reviewed_at')->nullable();
            $table->text('training_review_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('training_campaign_id');
            $table->dropConstrainedForeignId('training_reviewed_by');
            $table->dropColumn(['training_status', 'training_reviewed_at', 'training_review_notes']);
        });
    }
};
