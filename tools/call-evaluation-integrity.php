<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$counts = [
    'orphan_scorecard_campaigns' => DB::table('call_evaluation_scorecard_campaign as p')->leftJoin('call_evaluation_scorecards as s', 's.id', '=', 'p.call_evaluation_scorecard_id')->leftJoin('campaigns as c', 'c.id', '=', 'p.campaign_id')->whereNull('s.id')->orWhereNull('c.id')->count(),
    'orphan_criterion_results' => DB::table('call_evaluation_criterion_results as r')->leftJoin('call_evaluations as e', 'e.id', '=', 'r.call_evaluation_id')->whereNull('e.id')->count(),
    'duplicate_criterion_results' => DB::table('call_evaluation_criterion_results')->select('call_evaluation_id', 'criterion_snapshot_key')->groupBy('call_evaluation_id', 'criterion_snapshot_key')->havingRaw('count(*) > 1')->get()->count(),
    'submitted_without_final_values' => DB::table('call_evaluations')->where('status', 'submitted')->where(fn ($query) => $query->whereNull('percentage')->orWhereNull('result')->orWhereNull('finalized_at'))->count(),
    'drafts_with_finalized_at' => DB::table('call_evaluations')->where('status', 'draft')->whereNotNull('finalized_at')->count(),
    'invalid_statuses' => DB::table('call_evaluations')->whereNotIn('status', ['draft', 'submitted'])->count(),
];

echo json_encode($counts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
