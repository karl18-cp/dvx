<?php

namespace Database\Seeders;

use App\Models\AssessmentBankQuestion;
use App\Models\AssessmentCategory;
use App\Models\AssessmentSkill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssessmentQuestionBankQaSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::query()->whereIn('role', ['admin', 'manager'])->orderBy('id')->firstOrFail();
        $category = AssessmentCategory::query()->where('name', 'QA Pilot')->value('id');
        $skills = AssessmentSkill::query()->pluck('id', 'name');
        $questions = [
            ['easy', 'Grammar', 'multiple_choice', 'Choose the grammatically correct sentence.', ['She handles customer calls professionally.' => true, 'She handle customer calls professionally.' => false, 'She handling customer calls professionally.' => false]],
            ['easy', 'Grammar', 'true_false', 'A complete sentence should contain a subject and a predicate.', ['True' => true, 'False' => false]],
            ['easy', 'Grammar', 'multiple_selection', 'Select the words that are verbs.', ['call' => true, 'listen' => true, 'customer' => false, 'careful' => false]],
            ['easy', 'Call Handling', 'multiple_choice', 'What should you do first when answering a customer call?', ['Give a professional greeting.' => true, 'Immediately place the caller on hold.' => false, 'Ask for payment details.' => false]],
            ['easy', 'Call Handling', 'true_false', 'Active listening includes allowing the customer to finish speaking.', ['True' => true, 'False' => false]],
            ['easy', 'Objection Handling', 'short_answer', 'In one or two sentences, explain why acknowledging an objection is important.', []],
            ['easy', 'Objection Handling', 'multiple_choice', 'A customer says, “I am not interested.” What is the best initial response?', ['Acknowledge the concern and ask a relevant question.' => true, 'Argue that the customer is wrong.' => false, 'End the call immediately.' => false]],
            ['medium', 'Grammar', 'multiple_choice', 'Choose the sentence with correct subject–verb agreement.', ['The list of requirements is on the desk.' => true, 'The list of requirements are on the desk.' => false, 'The list of requirements be on the desk.' => false]],
            ['medium', 'Grammar', 'multiple_selection', 'Select the sentences that use punctuation correctly.', ['Thank you for calling; we appreciate your time.' => true, 'Before we continue, may I confirm your name?' => true, 'Hello customer how may I help you?' => false]],
            ['medium', 'Grammar', 'short_answer', 'Rewrite this sentence professionally: “You gotta wait because the system is down.”', []],
            ['medium', 'Call Handling', 'multiple_choice', 'A caller gives a long explanation. What is the best way to confirm understanding?', ['Summarize the key concern and ask for confirmation.' => true, 'Repeat every word exactly.' => false, 'Change the subject.' => false]],
            ['medium', 'Call Handling', 'multiple_selection', 'Which behaviors demonstrate active listening?', ['Paraphrasing the concern' => true, 'Asking focused follow-up questions' => true, 'Interrupting to save time' => false, 'Ignoring the caller’s tone' => false]],
            ['medium', 'Call Handling', 'true_false', 'A hold request should include the reason and expected wait time when possible.', ['True' => true, 'False' => false]],
            ['medium', 'Objection Handling', 'multiple_choice', 'A prospect says, “We already use another provider.” What is the strongest next step?', ['Ask what they value most about their current provider.' => true, 'Insult the competing provider.' => false, 'Repeat the same sales pitch louder.' => false]],
            ['hard', 'Grammar', 'multiple_choice', 'Choose the clearest and most concise sentence.', ['After reviewing the account, I identified the billing discrepancy.' => true, 'Due to the fact that I reviewed the account, a discrepancy in billing was identified by me.' => false, 'The account, which was reviewed, had a discrepancy that was billing-related in nature.' => false]],
            ['hard', 'Grammar', 'short_answer', 'Write a concise, professional response to a customer who misunderstood a policy.', []],
            ['hard', 'Call Handling', 'multiple_selection', 'A frustrated caller is escalating. Select the appropriate de-escalation actions.', ['Use a calm, measured tone.' => true, 'Acknowledge the impact of the issue.' => true, 'Promise an outcome you cannot authorize.' => false, 'Match the caller’s volume.' => false]],
            ['hard', 'Call Handling', 'short_answer', 'Describe how you would handle a caller whose request conflicts with company policy.', []],
            ['hard', 'Objection Handling', 'multiple_choice', 'A prospect repeatedly raises price concerns after value has been explained. What should you do?', ['Clarify the underlying budget concern and explore an appropriate option.' => true, 'Dismiss the concern as unimportant.' => false, 'Guarantee an unauthorized discount.' => false]],
            ['hard', 'Objection Handling', 'short_answer', 'Create a professional response to: “I need to speak with my spouse before deciding.”', []],
        ];

        DB::transaction(function () use ($questions, $creator, $category, $skills): void {
            foreach ($questions as $index => [$difficulty, $skill, $type, $text, $options]) {
                $question = AssessmentBankQuestion::query()->firstOrCreate(
                    ['question_text' => '[QA Seed '.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'] '.$text],
                    ['skill_id' => $skills[$skill] ?? null, 'category_id' => $category, 'question_type' => $type, 'difficulty' => $difficulty, 'points' => $difficulty === 'hard' ? 3 : ($difficulty === 'medium' ? 2 : 1), 'feedback' => 'QA seed question for Question Bank and random-pool testing.', 'status' => 'active', 'created_by' => $creator->id]
                );
                if ($question->options()->doesntExist()) {
                    $question->options()->createMany(collect($options)->map(fn (bool $correct, string $label) => ['option_text' => $label, 'is_correct' => $correct])->values()->map(fn (array $option, int $order) => [...$option, 'display_order' => $order + 1])->all());
                }
            }
        });
    }
}
