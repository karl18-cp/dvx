<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentBankQuestion;
use App\Models\AssessmentRandomPool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssessmentRandomPoolController extends Controller
{
    public function store(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $this->validated($request);
        $eligible = $this->eligible($assessment, $data);
        if ($eligible < $data['questions_to_select']) {
            return $this->notEnoughEligibleQuestions($eligible);
        }
        $assessment->randomPools()->create([...$data, 'display_order' => (int) $assessment->randomPools()->max('display_order') + 1]);

        return back();
    }

    public function update(Request $request, Assessment $assessment, AssessmentRandomPool $pool): RedirectResponse
    {
        abort_unless($pool->assessment_id === $assessment->id, 404);
        $data = $this->validated($request);
        $eligible = $this->eligible($assessment, $data);
        if ($eligible < $data['questions_to_select']) {
            return $this->notEnoughEligibleQuestions($eligible);
        }
        $pool->update($data);

        return back();
    }

    public function destroy(Assessment $assessment, AssessmentRandomPool $pool): RedirectResponse
    {
        abort_unless($pool->assessment_id === $assessment->id, 404);
        $pool->delete();

        return back();
    }

    private function validated(Request $request): array
    {
        return $request->validate(['skill_id' => ['nullable', 'integer', 'exists:assessment_skills,id'], 'category_id' => ['nullable', 'integer', 'exists:assessment_categories,id'], 'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])], 'question_type' => ['nullable', Rule::in(['multiple_choice', 'true_false', 'multiple_selection', 'short_answer'])], 'questions_to_select' => ['required', 'integer', 'min:1', 'max:100'], 'points_per_question' => ['required', 'numeric', 'gt:0']]);
    }

    private function eligible(Assessment $assessment, array $data): int
    {
        $assessment->loadMissing('campaigns:id');

        return AssessmentBankQuestion::query()->where('status', 'active')
            ->when(! $assessment->applies_to_all_campaigns, fn ($query) => $query->where(fn ($scope) => $scope
                ->where('applies_to_all_campaigns', true)
                ->orWhereHas('campaigns', fn ($campaigns) => $campaigns->whereIn('campaigns.id', $assessment->campaigns->modelKeys()))))
            ->when($data['skill_id'] ?? null, fn ($q, $v) => $q->where('skill_id', $v))->when($data['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))->when($data['difficulty'] ?? null, fn ($q, $v) => $q->where('difficulty', $v))->when($data['question_type'] ?? null, fn ($q, $v) => $q->where('question_type', $v))->count();
    }

    private function notEnoughEligibleQuestions(int $eligible): RedirectResponse
    {
        return back()->withErrors([
            'questions_to_select' => "Only {$eligible} active questions match this pool. Add matching questions to the Question Bank or reduce the number to select.",
        ])->withInput();
    }
}
