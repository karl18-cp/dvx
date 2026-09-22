<?php

use App\Models\CallEvaluationScorecard;
use App\Support\CallEvaluationScorecardValidator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$invalidOrdering = 0;
CallEvaluationScorecard::query()->with('categories.criteria')->each(function ($scorecard) use (&$invalidOrdering): void {
    $categoryOrder = $scorecard->categories->pluck('display_order')->map(fn ($value) => (int) $value)->all();
    $invalidOrdering += (int) ($categoryOrder !== [] && $categoryOrder !== range(1, count($categoryOrder)));
    foreach ($scorecard->categories as $category) {
        $criterionOrder = $category->criteria->pluck('display_order')->map(fn ($value) => (int) $value)->all();
        $invalidOrdering += (int) ($criterionOrder !== [] && $criterionOrder !== range(1, count($criterionOrder)));
    }
});

$validator = $app->make(CallEvaluationScorecardValidator::class);
$invalidActive = CallEvaluationScorecard::query()->where('status', 'active')->get()->filter(fn ($scorecard) => $validator->activationErrors($scorecard) !== [])->count();

$counts = [
    'orphan_categories' => DB::table('call_evaluation_scorecard_categories')->whereNotIn('scorecard_id', DB::table('call_evaluation_scorecards')->select('id'))->count(),
    'orphan_criteria' => DB::table('call_evaluation_scorecard_criteria')->whereNotIn('category_id', DB::table('call_evaluation_scorecard_categories')->select('id'))->count(),
    'invalid_campaign_pivots' => DB::table('call_evaluation_scorecard_campaign')->whereNotIn('call_evaluation_scorecard_id', DB::table('call_evaluation_scorecards')->select('id'))->orWhereNotIn('campaign_id', DB::table('campaigns')->select('id'))->count(),
    'duplicate_campaign_pivots' => DB::query()->fromSub(DB::table('call_evaluation_scorecard_campaign')->selectRaw('call_evaluation_scorecard_id, campaign_id, COUNT(*) AS occurrences')->groupBy('call_evaluation_scorecard_id', 'campaign_id')->having('occurrences', '>', 1), 'duplicates')->count(),
    'invalid_skill_relationships' => DB::table('call_evaluation_scorecard_criteria')->whereNotNull('skill_id')->whereNotIn('skill_id', DB::table('assessment_skills')->select('id'))->count(),
    'non_positive_criterion_points' => DB::table('call_evaluation_scorecard_criteria')->where('points_possible', '<=', 0)->count(),
    'invalid_active_scorecards' => $invalidActive,
    'corrupted_ordering_groups' => $invalidOrdering,
    'used_scorecards_missing_snapshots' => DB::table('call_evaluations')->whereNotNull('scorecard_id')->whereNull('scorecard_snapshot')->count(),
];

echo json_encode($counts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
