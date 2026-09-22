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
            $table->string('phone_normalized', 20)->nullable()->after('phone')->index();
            $table->text('applicant_update')->nullable()->after('internal_notes');
        });

        DB::table('job_applications')->orderBy('id')->each(function ($application): void {
            $digits = preg_replace('/\D+/', '', $application->phone) ?: '';
            DB::table('job_applications')->where('id', $application->id)->update([
                'phone_normalized' => strlen($digits) > 10 ? substr($digits, -10) : $digits,
            ]);
        });

    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropIndex(['phone_normalized']);
            $table->dropColumn(['phone_normalized', 'applicant_update']);
        });
    }
};
