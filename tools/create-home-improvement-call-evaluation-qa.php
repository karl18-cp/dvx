<?php

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentCategory;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSkill;
use App\Models\CallEvaluationScorecard;
use App\Models\Campaign;
use App\Models\CoachingRecord;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TrainingLibraryMaterial;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$result = DB::transaction(function (): array {
    $campaign = Campaign::query()->where('name', 'Home Improvement')->first()
        ?? Campaign::query()->create(['name' => 'Home Improvement', 'abbreviation' => 'HI', 'is_active' => true]);
    $team = Team::query()->where('campaign_id', $campaign->id)->orderBy('id')->first()
        ?? Team::query()->create(['name' => 'Home Improvement QA Team', 'campaign_id' => $campaign->id]);
    $admin = User::query()->whereIn('role', ['admin', 'manager'])->orderBy('id')->firstOrFail();
    $employee = User::query()->updateOrCreate(['email' => 'hi.call.qa@dvx.test'], [
        'username' => 'hi_call_qa', 'name' => 'Home Improvement Call QA', 'role' => 'agent',
        'status' => 'active', 'password' => Hash::make('DvxQa2026!'), 'email_verified_at' => now(),
    ]);
    TeamMember::query()->where('user_id', $employee->id)->delete();
    TeamMember::query()->create(['team_id' => $team->id, 'user_id' => $employee->id]);

    $skill = AssessmentSkill::query()->firstOrCreate(['name' => 'Call Handling'], ['description' => 'Core call handling behaviors.', 'is_active' => true]);
    $category = AssessmentCategory::query()->firstOrCreate(['name' => 'QA Call Evaluation']);
    $assessment = Assessment::query()->updateOrCreate(['title' => '[QA] Home Improvement Call Handling'], [
        'category_id' => $category->id, 'description' => 'Safe sample assessment for Home Improvement Call Evaluation QA.',
        'instructions' => 'Complete this QA-only assessment.', 'difficulty' => 'intermediate', 'passing_score' => 80,
        'time_limit_minutes' => 10, 'maximum_attempts' => 2, 'available_at' => now()->subHour(), 'due_at' => now()->addDays(14),
        'allow_retake' => true, 'randomize_questions' => false, 'randomize_answers' => false, 'show_correct_answers' => true,
        'status' => 'published', 'created_by' => $admin->id, 'updated_by' => $admin->id, 'published_at' => now(), 'applies_to_all_campaigns' => false,
    ]);
    $assessment->campaigns()->sync([$campaign->id]);
    if (! $assessment->questions()->exists()) {
        AssessmentQuestion::query()->create(['assessment_id' => $assessment->id, 'skill_id' => $skill->id, 'question_type' => 'true_false', 'question_text' => 'The agent should confirm the customer need before recommending the next step.', 'correct_answer' => 'true', 'points' => 5, 'display_order' => 1, 'is_required' => true]);
        AssessmentQuestion::query()->create(['assessment_id' => $assessment->id, 'skill_id' => $skill->id, 'question_type' => 'short_answer', 'question_text' => 'Describe one effective discovery question for a Home Improvement call.', 'points' => 5, 'display_order' => 2, 'is_required' => true]);
    }
    $assignment = AssessmentAssignment::query()->updateOrCreate(['assessment_id' => $assessment->id, 'employee_id' => $employee->id], [
        'campaign_id' => $campaign->id, 'campaign_name' => $campaign->name, 'source_team_id' => $team->id,
        'assigned_by' => $admin->id, 'assigned_at' => now(), 'available_from' => now()->subHour(), 'due_date' => now()->addDays(14), 'status' => 'assigned',
    ]);

    $material = TrainingLibraryMaterial::query()->updateOrCreate(['title' => '[QA] Home Improvement Discovery Basics'], [
        'skill_id' => $skill->id, 'description' => 'QA-only written lesson for testing employee development history.', 'type' => 'written',
        'content' => "Confirm the customer's project, timeline, property ownership, and decision-making process. Summarize the need before moving to the next step.",
        'duration_seconds' => 300, 'status' => 'active', 'created_by' => $admin->id, 'applies_to_all_campaigns' => false,
    ]);
    $material->campaigns()->sync([$campaign->id]);

    $coaching = CoachingRecord::query()->updateOrCreate(['employee_id' => $employee->id, 'summary' => '[QA] Practice discovery and call control'], [
        'campaign_id' => $campaign->id, 'campaign_name' => $campaign->name, 'team_id' => $team->id, 'team_name' => $team->name,
        'coach_id' => $admin->id, 'skill_id' => $skill->id, 'assessment_id' => $assessment->id, 'coaching_date' => today(),
        'type' => 'Call Handling Coaching', 'status' => 'open', 'strengths' => 'Professional greeting and clear tone.',
        'areas_for_improvement' => 'Ask more discovery questions before recommending a solution.',
        'action_plan' => 'Review the QA discovery lesson and practice two sample calls.', 'follow_up_date' => today()->addDays(7),
    ]);
    $coaching->trainingAssignments()->firstOrCreate(['library_material_id' => $material->id], ['is_required' => true, 'assigned_by' => $admin->id]);

    $scorecard = CallEvaluationScorecard::query()->updateOrCreate(['name' => '[QA] Home Improvement Call Scorecard'], [
        'description' => 'QA-only manual call evaluation Scorecard.', 'applies_to_all_campaigns' => false, 'status' => 'active',
        'passing_score' => 80, 'created_by' => $admin->id, 'updated_by' => $admin->id,
    ]);
    $scorecard->campaigns()->sync([$campaign->id]);
    if (! $scorecard->categories()->exists()) {
        $opening = $scorecard->categories()->create(['name' => 'Opening & Discovery', 'description' => 'Greeting, verification, and discovery.', 'display_order' => 1]);
        $opening->criteria()->create(['skill_id' => $skill->id, 'label' => 'Used a professional opening', 'guidance' => 'Verify that the approved greeting and introduction were used.', 'points_possible' => 10, 'is_required' => true, 'is_critical' => false, 'allows_na' => false, 'display_order' => 1]);
        $opening->criteria()->create(['skill_id' => $skill->id, 'label' => 'Asked relevant discovery questions', 'guidance' => 'Listen for project, timeline, ownership, and decision-maker questions.', 'points_possible' => 20, 'is_required' => true, 'is_critical' => false, 'allows_na' => false, 'display_order' => 2]);
        $compliance = $scorecard->categories()->create(['name' => 'Compliance & Closing', 'description' => 'Required disclosures and clear next steps.', 'display_order' => 2]);
        $compliance->criteria()->create(['skill_id' => $skill->id, 'label' => 'Followed required compliance language', 'guidance' => 'A score of zero triggers Critical Failure.', 'points_possible' => 40, 'is_required' => true, 'is_critical' => true, 'allows_na' => false, 'display_order' => 1]);
        $compliance->criteria()->create(['skill_id' => $skill->id, 'label' => 'Confirmed the next step', 'guidance' => 'The customer should understand what happens after the call.', 'points_possible' => 30, 'is_required' => true, 'is_critical' => false, 'allows_na' => true, 'display_order' => 2]);
    }

    return ['employee_id' => $employee->id, 'employee' => $employee->name, 'username' => $employee->username, 'email' => $employee->email, 'campaign' => $campaign->name, 'team' => $team->name, 'assessment_id' => $assessment->id, 'assessment_questions' => $assessment->questions()->count(), 'assignment_id' => $assignment->id, 'training_material_id' => $material->id, 'coaching_id' => $coaching->id, 'coaching_training_count' => $coaching->trainingAssignments()->count(), 'scorecard_id' => $scorecard->id, 'scorecard_categories' => $scorecard->categories()->count(), 'scorecard_criteria' => $scorecard->criteria()->count(), 'password_verified' => Hash::check('DvxQa2026!', $employee->password)];
});

$audioDirectory = storage_path('app/qa-samples');
if (! is_dir($audioDirectory)) {
    mkdir($audioDirectory, 0775, true);
}
$audioPath = $audioDirectory.'/home-improvement-call-qa.wav';
if (! file_exists($audioPath)) {
    $sampleRate = 8000;
    $seconds = 3;
    $samples = $sampleRate * $seconds;
    $data = '';
    for ($i = 0; $i < $samples; $i++) {
        $sample = (int) (sin(2 * M_PI * 440 * $i / $sampleRate) * 2500);
        $data .= pack('v', $sample & 0xFFFF);
    }
    $header = 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16).'data'.pack('V', strlen($data));
    file_put_contents($audioPath, $header.$data);
}
$result['sample_recording'] = $audioPath;

echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
