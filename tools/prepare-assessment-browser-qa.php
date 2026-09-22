<?php

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentCategory;
use App\Models\AssessmentSkill;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$password = 'DvxQA!2026Pilot#';

DB::transaction(function () use ($password): void {
    $admin = User::query()->firstOrCreate(
        ['email' => 'qa.admin@dvx.test'],
        ['username' => 'qa_admin', 'name' => 'QA Assessment Admin', 'role' => 'admin', 'status' => 'active', 'email_verified_at' => now(), 'password' => Hash::make($password)],
    );
    $employee = User::query()->firstOrCreate(
        ['email' => 'qa.employee@dvx.test'],
        ['username' => 'qa_employee', 'name' => 'QA Assessment Employee', 'role' => 'agent', 'status' => 'active', 'email_verified_at' => now(), 'password' => Hash::make($password)],
    );
    $admin->update(['username' => 'qa_admin', 'name' => 'QA Assessment Admin', 'role' => 'admin', 'status' => 'active', 'email_verified_at' => $admin->email_verified_at ?? now(), 'password' => Hash::make($password)]);
    $employee->update(['username' => 'qa_employee', 'name' => 'QA Assessment Employee', 'role' => 'agent', 'status' => 'active', 'email_verified_at' => $employee->email_verified_at ?? now(), 'password' => Hash::make($password)]);

    $campaign = Campaign::query()->firstOrCreate(['name' => 'QA Assessment Campaign'], ['abbreviation' => 'QA-ASM']);
    $team = Team::query()->firstOrCreate(['name' => 'QA Assessment Team'], ['campaign_id' => $campaign->id]);
    TeamMember::query()->updateOrCreate(['user_id' => $employee->id], ['team_id' => $team->id]);
    $employee->update(['team' => $team->name]);

    $category = AssessmentCategory::query()->firstOrCreate(['name' => 'QA Pilot'], ['description' => 'Temporary browser QA assessments.', 'is_active' => true]);
    $skills = collect(['Grammar', 'Call Handling', 'Objection Handling'])->mapWithKeys(fn (string $name) => [$name => AssessmentSkill::query()->firstOrCreate(['name' => $name], ['is_active' => true])]);
    $assessment = Assessment::query()->firstOrCreate(['title' => 'QA — Assessment Pilot Test'], [
        'category_id' => $category->id, 'description' => 'Temporary end-to-end browser QA assessment.', 'instructions' => 'Complete the training, answer every question, and submit.',
        'difficulty' => 'beginner', 'passing_score' => 80, 'time_limit_minutes' => 10, 'maximum_attempts' => 2,
        'allow_retake' => true, 'randomize_questions' => true, 'randomize_answers' => true, 'show_correct_answers' => true,
        'status' => 'published', 'created_by' => $admin->id, 'updated_by' => $admin->id, 'published_at' => now(),
    ]);
    abort_if($assessment->attempts()->exists(), 409, 'The QA assessment already has attempts; preparation stopped to preserve QA history.');
    $assessment->update(['category_id' => $category->id, 'passing_score' => 80, 'time_limit_minutes' => 10, 'maximum_attempts' => 2, 'allow_retake' => true, 'randomize_questions' => true, 'randomize_answers' => true, 'show_correct_answers' => true, 'status' => 'published', 'updated_by' => $admin->id, 'published_at' => $assessment->published_at ?? now()]);

    if (! $assessment->trainingMaterials()->exists()) {
        $assessment->trainingMaterials()->create(['title' => 'QA Required Training', 'description' => 'Lightweight browser QA training material.', 'type' => 'written', 'content' => 'Review the assessment instructions. Choose answers carefully and confirm autosave before submitting.', 'is_required' => true, 'required_completion_percentage' => 100, 'display_order' => 1, 'is_active' => true, 'created_by' => $admin->id]);
    }
    if (! $assessment->questions()->exists()) {
        $definitions = [
            ['Grammar: choose the correctly written greeting.', 'multiple_choice', 'Grammar', [['Good morning, how may I help you?', true], ['good morning how may i help you', false], ['Morning help?', false]]],
            ['Grammar: choose the professional closing.', 'multiple_choice', 'Grammar', [['Thank you for calling. Have a great day.', true], ['Okay, bye.', false], ['We are done.', false]]],
            ['Call Handling: what should happen first?', 'multiple_choice', 'Call Handling', [['Verify the customer appropriately.', true], ['End the call.', false], ['Place the caller on hold without notice.', false]]],
            ['A clear greeting helps establish professionalism.', 'true_false', 'Call Handling', [['True', true], ['False', false]]],
            ['It is acceptable to promise an outcome you cannot guarantee.', 'true_false', 'Objection Handling', [['True', false], ['False', true]]],
            ['Select all professional call behaviors.', 'multiple_selection', 'Call Handling', [['Active listening', true], ['Clear explanations', true], ['Interrupting the customer', false], ['Documenting key details', true]]],
            ['Select all appropriate objection-handling actions.', 'multiple_selection', 'Objection Handling', [['Acknowledge the concern', true], ['Ask clarifying questions', true], ['Argue with the customer', false], ['Offer an accurate next step', true]]],
            ['Write a concise response to a customer concerned about price.', 'short_answer', 'Objection Handling', []],
            ['Explain how you would confirm the customer understood the resolution.', 'short_answer', 'Call Handling', []],
        ];
        foreach ($definitions as $index => [$text, $type, $skill, $options]) {
            $question = $assessment->questions()->create(['skill_id' => $skills[$skill]->id, 'question_text' => $text, 'question_type' => $type, 'points' => 1, 'feedback' => 'Review the related QA training guidance.', 'display_order' => $index + 1, 'is_required' => true]);
            foreach ($options as $optionIndex => [$optionText, $correct]) {
                $question->options()->create(['option_text' => $optionText, 'is_correct' => $correct, 'display_order' => $optionIndex + 1]);
            }
        }
    }

    AssessmentAssignment::query()->updateOrCreate(
        ['assessment_id' => $assessment->id, 'employee_id' => $employee->id],
        ['assigned_by' => $admin->id, 'assigned_at' => now(), 'available_from' => now()->subMinute(), 'due_date' => now()->addDays(7), 'status' => 'assigned', 'source_team_id' => $team->id],
    );

    echo "employee_email={$employee->email}\nadmin_email={$admin->email}\nteam={$team->name}\nassessment={$assessment->title}\nquestions={$assessment->questions()->count()}\nrequired_materials={$assessment->trainingMaterials()->where('is_required', true)->count()}\nassignments=".AssessmentAssignment::query()->where('assessment_id', $assessment->id)->count()."\n";
});
