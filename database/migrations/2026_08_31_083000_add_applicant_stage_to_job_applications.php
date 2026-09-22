<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->string('applicant_stage', 30)->default('for_screening')->after('application_status')->index();
        });

        DB::table('job_applications')->where('application_status', 'for_interview')->update(['applicant_stage' => 'for_final_interview']);
        DB::table('job_applications')->where('application_status', 'hired')->update(['applicant_stage' => 'passed']);
        DB::table('job_applications')->where('application_status', 'rejected')->update(['applicant_stage' => 'failed']);
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropIndex(['applicant_stage']);
            $table->dropColumn('applicant_stage');
        });
    }
};
