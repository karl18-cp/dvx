<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Existing assignment overrides were stored as Philippine wall-clock
        // values in UTC columns. Normalize them once; new writes are converted
        // from Asia/Manila to UTC by the assignment controller.
        $this->shift(-8);
    }

    public function down(): void
    {
        $this->shift(8);
    }

    private function shift(int $hours): void
    {
        DB::table('assessment_assignments')->select(['id', 'available_from', 'due_date'])
            ->where(fn ($query) => $query->whereNotNull('available_from')->orWhereNotNull('due_date'))
            ->orderBy('id')->chunkById(500, function ($assignments) use ($hours): void {
                foreach ($assignments as $assignment) {
                    DB::table('assessment_assignments')->where('id', $assignment->id)->update([
                        'available_from' => $assignment->available_from ? Carbon::parse($assignment->available_from)->addHours($hours) : null,
                        'due_date' => $assignment->due_date ? Carbon::parse($assignment->due_date)->addHours($hours) : null,
                    ]);
                }
            });
    }
};
