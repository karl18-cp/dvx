<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$checks = [
    'duplicate_attempt_numbers' => 'select count(1) from (select assignment_id, attempt_number, count(1) c from assessment_attempts group by assignment_id, attempt_number having c > 1) x',
    'duplicate_answers' => 'select count(1) from (select attempt_id, question_id, count(1) c from assessment_answers group by attempt_id, question_id having c > 1) x',
    'duplicate_skill_results' => 'select count(1) from (select attempt_id, skill_id, count(1) c from assessment_skill_results group by attempt_id, skill_id having c > 1) x',
    'orphan_answers' => 'select count(1) from assessment_answers a left join assessment_attempts t on t.id = a.attempt_id where t.id is null',
    'orphan_skill_results' => 'select count(1) from assessment_skill_results s left join assessment_attempts t on t.id = s.attempt_id where t.id is null',
    'submitted_missing_timestamp' => "select count(1) from assessment_attempts where status in ('passed','failed','pending_review') and submitted_at is null",
    'submitted_missing_question_snapshot' => "select count(1) from assessment_attempts where status in ('passed','failed','pending_review') and question_snapshot is null",
    'invalid_manual_points' => 'select count(1) from assessment_answers where requires_manual_review = 1 and points_awarded < 0',
    'manual_points_above_snapshot_maximum' => "select count(1) from assessment_answers where requires_manual_review = 1 and points_awarded is not null and points_awarded > cast(json_unquote(json_extract(question_snapshot, '$.points')) as decimal(10,2))",
];

echo 'mysql_version='.DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION).PHP_EOL;
foreach ($checks as $name => $sql) {
    if (str_contains($sql, 'assessment_skill_results') && ! Schema::hasTable('assessment_skill_results')) {
        echo $name.'=NOT_RUN_MISSING_TABLE'.PHP_EOL;

        continue;
    }
    echo $name.'='.DB::scalar($sql).PHP_EOL;
}
