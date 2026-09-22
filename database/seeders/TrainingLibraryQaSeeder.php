<?php

namespace Database\Seeders;

use App\Models\AssessmentCategory;
use App\Models\AssessmentSkill;
use App\Models\TrainingLibraryMaterial;
use App\Models\User;
use Illuminate\Database\Seeder;

class TrainingLibraryQaSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::query()->whereIn('role', ['admin', 'manager'])->orderBy('id')->firstOrFail();
        $category = AssessmentCategory::query()->where('name', 'QA Pilot')->value('id');
        $skills = AssessmentSkill::query()->pluck('id', 'name');

        $lessons = [
            ['Professional Call Opening', 'Call Handling', 300, "Professional Call Opening\n\n1. Answer promptly and introduce yourself.\n2. State the company name clearly.\n3. Ask how you may assist the caller.\n4. Confirm the caller's name and preferred form of address.\n\nExample:\nGood morning. Thank you for calling DVX. This is Alex. May I know whom I am speaking with and how I can help you today?"],
            ['Active Listening Essentials', 'Call Handling', 420, "Active Listening Essentials\n\nGive the caller your full attention. Avoid interrupting. Listen for both facts and emotion. Paraphrase the concern, then confirm your understanding before proposing a solution.\n\nUseful phrase:\nTo make sure I understood correctly, your main concern is... Is that right?"],
            ['Placing a Customer on Hold', 'Call Handling', 240, "Placing a Customer on Hold\n\nExplain why the hold is necessary, request permission, and provide an estimated wait time. Return within the promised interval. Thank the customer for waiting and summarize what you found."],
            ['Handling "Not Interested"', 'Objection Handling', 360, "Handling the \"Not Interested\" Objection\n\nDo not argue. Acknowledge the response and ask one concise discovery question. Connect the customer's stated concern to a relevant benefit. If interest remains low, close professionally and preserve the relationship."],
            ['Handling an Existing Provider Objection', 'Objection Handling', 420, "Existing Provider Objection\n\nWhen a prospect already has a provider, ask what works well and what they would improve. Avoid criticizing competitors. Identify a genuine gap before explaining how DVX may add value."],
            ['Spouse or Decision-Maker Objection', 'Objection Handling', 300, "Decision-Maker Objection\n\nRespect the customer's need to consult another person. Ask what information would help that discussion, offer a concise summary, and agree on a reasonable follow-up time. Never pressure the customer into an immediate decision."],
            ['Professional Grammar for Customer Messages', 'Grammar', 480, "Professional Grammar\n\nUse complete sentences, consistent verb tense, and correct subject-verb agreement. Prefer direct language over unnecessary wording. Review names, dates, amounts, and punctuation before sending any customer message.\n\nExample:\nWe have reviewed your account and corrected the billing discrepancy."],
            ['Writing Clear Follow-Up Notes', 'Grammar', 360, "Clear Follow-Up Notes\n\nRecord the customer's concern, actions taken, promised next step, owner, and deadline. Use objective language. Avoid slang, unexplained abbreviations, and personal judgments. Notes should allow another employee to continue the case without asking the customer to repeat everything."],
        ];

        foreach ($lessons as [$title, $skill, $duration, $content]) {
            TrainingLibraryMaterial::query()->firstOrCreate(
                ['title' => $title],
                [
                    'description' => 'QA seed lesson for testing reusable training attachments and progress.',
                    'type' => 'written',
                    'category_id' => $category,
                    'skill_id' => $skills[$skill] ?? null,
                    'content' => $content,
                    'duration_seconds' => $duration,
                    'status' => 'active',
                    'created_by' => $creator->id,
                ]
            );
        }
    }
}
