<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssessmentQuestionController extends Controller
{
    public function store(Request $request, Assessment $assessment): RedirectResponse
    {
        [$data,$options] = $this->validated($request);
        DB::transaction(function () use ($assessment, $data, $options): void {
            $question = $assessment->questions()->create([...$data, 'display_order' => (int) $assessment->questions()->max('display_order') + 1]);
            $question->options()->createMany($options);
        });

        return back()->with('status', 'Question added.');
    }

    public function update(Request $request, Assessment $assessment, AssessmentQuestion $question): RedirectResponse
    {
        $this->belongsTo($assessment, $question);
        [$data,$options] = $this->validated($request);
        DB::transaction(function () use ($question, $data, $options): void {
            $question->update($data);
            $question->options()->delete();
            $question->options()->createMany($options);
        });

        return back()->with('status', 'Question updated.');
    }

    public function destroy(Assessment $assessment, AssessmentQuestion $question): RedirectResponse
    {
        $this->belongsTo($assessment, $question);
        abort_if(DB::table('assessment_answers')->where('question_id', $question->id)->exists(), 422, 'Questions with historical answers cannot be deleted.');
        $question->delete();

        return back()->with('status', 'Question deleted.');
    }

    public function duplicate(Assessment $assessment, AssessmentQuestion $question): RedirectResponse
    {
        $this->belongsTo($assessment, $question);
        DB::transaction(function () use ($assessment, $question): void {
            $copy = $question->replicate(['display_order']);
            $copy->display_order = (int) $assessment->questions()->max('display_order') + 1;
            $copy->question_text .= ' (Copy)';
            $copy->save();
            $copy->options()->createMany($question->options()->get(['option_text', 'is_correct', 'display_order'])->toArray());
        });

        return back()->with('status', 'Question duplicated.');
    }

    public function reorder(Request $request, Assessment $assessment): RedirectResponse
    {
        $ids = $request->validate(['question_ids' => ['required', 'array'], 'question_ids.*' => ['integer', 'distinct']])['question_ids'];
        abort_unless($assessment->questions()->whereIn('id', $ids)->count() === count($ids), 422);
        DB::transaction(fn () => collect($ids)->each(fn ($id, $i) => $assessment->questions()->whereKey($id)->update(['display_order' => $i + 1])));

        return back();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'question_text' => ['required', 'string', 'max:10000'], 'question_type' => ['required', Rule::in(['multiple_choice', 'true_false', 'multiple_selection', 'short_answer'])],
            'points' => ['required', 'numeric', 'gt:0', 'max:999999'], 'skill_id' => ['nullable', 'integer', Rule::exists('assessment_skills', 'id')->where('is_active', true)],
            'feedback' => ['nullable', 'string', 'max:10000'], 'is_required' => ['required', 'boolean'],
            'options' => ['nullable', 'array', 'max:20'], 'options.*.option_text' => ['required_with:options', 'string', 'max:2000'], 'options.*.is_correct' => ['required_with:options', 'boolean'],
        ]);
        $type = $data['question_type'];
        $options = array_values($data['options'] ?? []);
        if ($type === 'true_false') {
            $labels = collect($options)->map(fn ($option) => strtolower(trim($option['option_text'])));
            if (count($options) !== 2 || $labels->sort()->values()->all() !== ['false', 'true']) {
                throw ValidationException::withMessages(['options' => 'True/False questions require exactly the True and False options.']);
            }
            $byLabel = collect($options)->keyBy(fn ($option) => strtolower(trim($option['option_text'])));
            $options = [
                ['option_text' => 'True', 'is_correct' => (bool) $byLabel['true']['is_correct']],
                ['option_text' => 'False', 'is_correct' => (bool) $byLabel['false']['is_correct']],
            ];
        }
        if ($type === 'short_answer') {
            $options = [];
        }
        if (in_array($type, ['multiple_choice', 'multiple_selection'], true) && count($options) < 2) {
            throw ValidationException::withMessages(['options' => 'At least two answer choices are required.']);
        }
        $correct = collect($options)->where('is_correct', true)->count();
        if (in_array($type, ['multiple_choice', 'true_false'], true) && $correct !== 1) {
            throw ValidationException::withMessages(['options' => 'Exactly one correct answer is required.']);
        }
        if ($type === 'multiple_selection' && $correct < 1) {
            throw ValidationException::withMessages(['options' => 'Select at least one correct answer.']);
        }
        unset($data['options']);
        $options = collect($options)->values()->map(fn ($option, $i) => [...$option, 'display_order' => $i + 1])->all();

        return [$data, $options];
    }

    private function belongsTo(Assessment $assessment, AssessmentQuestion $question): void
    {
        abort_unless($question->assessment_id === $assessment->id, 404);
    }
}
