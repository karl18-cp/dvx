<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->string('recruitment_email_status')->default('not_sent');
            $table->timestamp('recruitment_emailed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', fn (Blueprint $table) => $table->dropColumn(['recruitment_email_status', 'recruitment_emailed_at']));
    }
};
