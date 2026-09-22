<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('assessment_attempts')
            ->select(['id', 'started_at', 'created_at', 'submitted_at', 'time_taken_seconds'])
            ->whereColumn('started_at', '>', 'created_at')
            ->orderBy('id')
            ->chunkById(500, function ($attempts): void {
                foreach ($attempts as $attempt) {
                    $startedAt = $attempt->submitted_at !== null && $attempt->time_taken_seconds !== null
                        ? Carbon::parse($attempt->submitted_at)->subSeconds((int) $attempt->time_taken_seconds)
                        : Carbon::parse($attempt->created_at);

                    DB::table('assessment_attempts')->where('id', $attempt->id)->update(['started_at' => $startedAt]);
                }
            });
    }

    public function down(): void
    {
        // This repairs invalid timestamps from legacy timezone handling and
        // intentionally does not recreate corrupt future start times.
    }
};
