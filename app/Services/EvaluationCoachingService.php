<?php

namespace App\Services;

use App\Models\CallEvaluation;
use App\Models\CoachingRecord;
use Illuminate\Support\Facades\DB;

class EvaluationCoachingService
{
    public function sync(CallEvaluation $evaluation): CoachingRecord
    {
        return DB::transaction(function () use ($evaluation) {
            $evaluation = CallEvaluation::query()->lockForUpdate()->findOrFail($evaluation->id);
            abort_unless($evaluation->status === 'submitted', 409, 'Only submitted evaluations can create coaching records.');
            $record = CoachingRecord::query()->firstOrCreate(['call_evaluation_id' => $evaluation->id], [
                'employee_id' => $evaluation->employee_id,
                'campaign_id' => $evaluation->campaign_id, 'campaign_name' => $evaluation->campaign_name,
                'team_id' => $evaluation->team_id, 'team_name' => $evaluation->team_name,
                'coach_id' => $evaluation->evaluator_id,
                'coaching_date' => $evaluation->finalized_at?->toDateString() ?? today(), 'type' => 'Call Handling Coaching', 'status' => 'open',
                'summary' => "QA Evaluation: {$evaluation->scorecard_name} ({$evaluation->percentage}%)",
                'strengths' => $evaluation->strengths,
                'areas_for_improvement' => $evaluation->areas_for_improvement,
                'action_plan' => $evaluation->recommended_action ?? $evaluation->overall_feedback,
            ]);
            if ($record->wasRecentlyCreated) {
                DB::table('assessment_activity_logs')->insert(['actor_id' => $evaluation->evaluator_id, 'action' => 'Coaching Created from Evaluation', 'target_type' => CoachingRecord::class, 'target_id' => $record->id, 'metadata' => json_encode(['call_evaluation_id' => $evaluation->id, 'automatic' => true]), 'created_at' => now()]);
            }

            return $record;
        });
    }
}
