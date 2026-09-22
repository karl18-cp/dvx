<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CampaignIntegrityService
{
    /** @return array<string, int|string> */
    public function inspect(): array
    {
        $cutoff = CarbonImmutable::parse(config('campaigns.snapshot_cutoff'), 'UTC');
        $result = [
            'snapshot_cutoff_utc' => $cutoff->toDateTimeString(),
            'orphan_team_campaigns' => DB::table('teams as t')->leftJoin('campaigns as c', 'c.id', '=', 't.campaign_id')->whereNull('c.id')->count(),
        ];

        foreach ([
            ['assessment_campaign', 'assessment_id', 'assessments'],
            ['training_library_material_campaign', 'training_library_material_id', 'training_library_materials'],
            ['assessment_bank_question_campaign', 'assessment_bank_question_id', 'assessment_bank_questions'],
        ] as [$pivot, $contentKey, $contentTable]) {
            $result['orphan_'.$pivot] = DB::table($pivot.' as p')
                ->leftJoin($contentTable.' as x', 'x.id', '=', 'p.'.$contentKey)
                ->leftJoin('campaigns as c', 'c.id', '=', 'p.campaign_id')
                ->where(fn (Builder $query) => $query->whereNull('x.id')->orWhereNull('c.id'))
                ->count();
            $result['duplicate_'.$pivot] = DB::query()->fromSub(
                DB::table($pivot)->select($contentKey, 'campaign_id')->groupBy($contentKey, 'campaign_id')->havingRaw('count(*) > 1'),
                'duplicates'
            )->count();
        }

        foreach (['assessment_assignments', 'assessment_attempts', 'coaching_records'] as $table) {
            $result['campaign_era_invalid_'.$table] = $this->invalidSnapshots($table)->where('s.created_at', '>=', $cutoff)->count();
            $result['legacy_unknown_'.$table] = DB::table($table.' as s')->where('s.created_at', '<', $cutoff)->whereNull('campaign_id')->whereNull('campaign_name')->count();
            $result['orphan_campaign_reference_'.$table] = DB::table($table.' as s')->leftJoin('campaigns as c', 'c.id', '=', 's.campaign_id')->whereNotNull('s.campaign_id')->whereNull('c.id')->count();
            $result['missing_immutable_campaign_name_'.$table] = DB::table($table)->whereNotNull('campaign_id')->whereNull('campaign_name')->count();
        }

        $result['campaign_era_assignment_attempt_mismatches'] = DB::table('assessment_attempts as attempt')
            ->join('assessment_assignments as assignment', 'assignment.id', '=', 'attempt.assignment_id')
            ->where('attempt.created_at', '>=', $cutoff)
            ->where(fn (Builder $query) => $query
                ->whereColumn('attempt.campaign_id', '<>', 'assignment.campaign_id')
                ->orWhereColumn('attempt.campaign_name', '<>', 'assignment.campaign_name')
                ->orWhereRaw('(attempt.campaign_id is null) <> (assignment.campaign_id is null)')
                ->orWhereRaw('(attempt.campaign_name is null) <> (assignment.campaign_name is null)'))
            ->count();

        return $result;
    }

    private function invalidSnapshots(string $table): Builder
    {
        return DB::table($table.' as s')
            ->leftJoin('campaigns as c', 'c.id', '=', 's.campaign_id')
            ->where(fn (Builder $query) => $query
                ->whereNull('s.campaign_id')
                ->orWhereNull('s.campaign_name')
                ->orWhereNull('c.id')
                ->orWhereColumn('s.campaign_name', '<>', 'c.name'));
    }
}
