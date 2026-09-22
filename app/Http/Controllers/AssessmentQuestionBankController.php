<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentBankQuestion;
use App\Models\AssessmentCategory;
use App\Models\AssessmentSkill;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentQuestionBankController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'skill', 'category', 'difficulty', 'type', 'status', 'campaign']);
        $target = $request->integer('assessment') ? Assessment::query()->with('campaigns:id')->find($request->integer('assessment')) : null;
        $questions = AssessmentBankQuestion::query()->with(['skill:id,name', 'category:id,name', 'campaigns:id,name,abbreviation', 'options:id,bank_question_id,option_text,is_correct,display_order'])->withCount(['options', 'options as usage_count' => fn ($q) => $q->whereRaw('0 = 1')])
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('question_text', 'like', '%'.addcslashes($v, '%_\\').'%'))
            ->when($filters['skill'] ?? null, fn ($q, $v) => $q->where('skill_id', $v))->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['difficulty'] ?? null, fn ($q, $v) => $q->where('difficulty', $v))->when($filters['type'] ?? null, fn ($q, $v) => $q->where('question_type', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($filters['campaign'] ?? null, fn ($q, $v) => $q->where(fn ($scope) => $scope->where('applies_to_all_campaigns', true)->orWhereHas('campaigns', fn ($c) => $c->whereKey($v))))
            ->when($target && ! $target->applies_to_all_campaigns, fn ($q) => $q->where(fn ($scope) => $scope->where('applies_to_all_campaigns', true)->orWhereHas('campaigns', fn ($c) => $c->whereIn('campaigns.id', $target->campaigns->modelKeys()))))
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('assessments/question-bank', ['questions' => $questions, 'filters' => $filters, 'target_assessment_id' => $request->integer('assessment') ?: null, 'skills' => AssessmentSkill::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']), 'categories' => AssessmentCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']), 'campaigns' => Campaign::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $options] = $this->validated($request);
        DB::transaction(function () use ($data, $options, $request): void {
            $campaignIds = $data['campaign_ids'] ?? [];
            unset($data['campaign_ids']);
            $question = AssessmentBankQuestion::query()->create([...$data, 'created_by' => $request->user()->id, 'status' => 'active']);
            $question->campaigns()->sync($question->applies_to_all_campaigns ? [] : $campaignIds);
            $question->options()->createMany($options);
            $this->log($request, 'Question Bank Question Created', $question);
        });

        return back()->with('status', 'Question created.');
    }

    public function import(Request $request): RedirectResponse
    {
        $batch = $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('assessment_categories', 'id')->where('is_active', true)],
            'skill_id' => ['required', 'integer', Rule::exists('assessment_skills', 'id')->where('is_active', true)],
            'applies_to_all_campaigns' => ['required', 'boolean'],
            'campaign_ids' => ['required_if:applies_to_all_campaigns,false', 'array'],
            'campaign_ids.*' => ['integer', 'distinct', Rule::exists('campaigns', 'id')->where('is_active', true)],
            'questions' => ['required', 'array', 'min:1', 'max:100'],
            'questions.*' => ['required', 'array'],
        ]);
        $validated = [];
        foreach ($batch['questions'] as $index => $question) {
            $single = Request::create('/', 'POST', [
                ...$question,
                'category_id' => $batch['category_id'], 'skill_id' => $batch['skill_id'],
                'applies_to_all_campaigns' => $batch['applies_to_all_campaigns'], 'campaign_ids' => $batch['campaign_ids'] ?? [],
            ]);
            try {
                $validated[] = $this->validated($single);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $key) => ["questions.{$index}.{$key}" => $messages])->all());
            }
        }
        $created = DB::transaction(function () use ($validated, $request): int {
            $count = 0;
            foreach ($validated as [$data, $options]) {
                $data['question_text'] = trim($data['question_text']);
                $campaignIds = $data['campaign_ids'] ?? [];
                unset($data['campaign_ids']);
                $question = AssessmentBankQuestion::query()->create([...$data, 'created_by' => $request->user()->id, 'status' => 'active']);
                $question->campaigns()->sync($question->applies_to_all_campaigns ? [] : $campaignIds);
                $question->options()->createMany($options);
                $this->log($request, 'Question Bank Question Imported', $question);
                $count++;
            }

            return $count;
        });

        return back()->with('status', "Imported {$created} question(s) into the selected category and skill.");
    }

    public function update(Request $request, AssessmentBankQuestion $question): RedirectResponse
    {
        [$data, $options] = $this->validated($request);
        DB::transaction(function () use ($data, $options, $question, $request): void {
            $campaignIds = $data['campaign_ids'] ?? [];
            unset($data['campaign_ids']);
            $question->update($data);
            $question->campaigns()->sync($question->applies_to_all_campaigns ? [] : $campaignIds);
            $question->options()->delete();
            $question->options()->createMany($options);
            $this->log($request, 'Question Bank Question Updated', $question);
        });

        return back()->with('status', 'Question updated.');
    }

    public function duplicate(Request $request, AssessmentBankQuestion $question): RedirectResponse
    {
        DB::transaction(function () use ($request, $question): void {
            $question->load(['options', 'campaigns:id']);
            $copy = $question->replicate(['status', 'created_by']);
            $copy->question_text = 'Copy of '.$question->question_text;
            $copy->status = 'active';
            $copy->created_by = $request->user()->id;
            $copy->save();
            $copy->campaigns()->sync($question->campaigns->modelKeys());
            $copy->options()->createMany($question->options->map->only(['option_text', 'is_correct', 'display_order'])->all());
            $this->log($request, 'Question Bank Question Duplicated', $copy);
        });

        return back();
    }

    public function archive(Request $request, AssessmentBankQuestion $question): RedirectResponse
    {
        $question->update(['status' => 'archived']);
        $this->log($request, 'Question Bank Question Archived', $question);

        return back();
    }

    public function addToAssessment(Request $request, Assessment $assessment): RedirectResponse
    {
        $ids = $request->validate(['question_ids' => ['required', 'array', 'min:1'], 'question_ids.*' => ['integer', 'distinct', Rule::exists('assessment_bank_questions', 'id')->where('status', 'active')]])['question_ids'];
        $assessment->load('campaigns:id');
        if (! $assessment->applies_to_all_campaigns) {
            $compatible = AssessmentBankQuestion::query()->whereIn('id', $ids)->where(fn ($scope) => $scope->where('applies_to_all_campaigns', true)->orWhereHas('campaigns', fn ($c) => $c->whereIn('campaigns.id', $assessment->campaigns->modelKeys())))->count();
            abort_if($compatible !== count($ids), 422, 'One or more questions are outside this assessment campaign scope.');
        }
        DB::transaction(function () use ($ids, $assessment): void {
            $order = (int) $assessment->questions()->whereNull('generated_for_attempt_id')->max('display_order');
            AssessmentBankQuestion::query()->with('options')->whereIn('id', $ids)->orderBy('id')->get()->each(function ($bank) use ($assessment, &$order): void {
                $question = $assessment->questions()->create(['skill_id' => $bank->skill_id, 'source_bank_question_id' => $bank->id, 'question_text' => $bank->question_text, 'question_type' => $bank->question_type, 'points' => $bank->points, 'feedback' => $bank->feedback, 'display_order' => ++$order, 'is_required' => true]);
                $question->options()->createMany($bank->options->map->only(['option_text', 'is_correct', 'display_order'])->all());
            });
        });

        return to_route('assessments.builder', $assessment)->with('status', count($ids).' question(s) added from the Question Bank.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate(['question_text' => ['required', 'string', 'max:10000'], 'question_type' => ['required', Rule::in(['multiple_choice', 'true_false', 'multiple_selection', 'short_answer'])], 'skill_id' => ['nullable', 'integer', 'exists:assessment_skills,id'], 'category_id' => ['nullable', 'integer', 'exists:assessment_categories,id'], 'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])], 'points' => ['required', 'numeric', 'gt:0'], 'feedback' => ['nullable', 'string'], 'options' => ['nullable', 'array', 'max:20'], 'options.*.option_text' => ['required_with:options', 'string'], 'options.*.is_correct' => ['required_with:options', 'boolean'], 'applies_to_all_campaigns' => ['sometimes', 'boolean'], 'campaign_ids' => ['required_if:applies_to_all_campaigns,false', 'array'], 'campaign_ids.*' => ['integer', 'distinct', Rule::exists('campaigns', 'id')->where('is_active', true)]]);
        $options = array_values($data['options'] ?? []);
        $type = $data['question_type'];
        if ($type === 'true_false') {
            $correct = collect($options)->firstWhere('is_correct', true)['option_text'] ?? null;
            throw_if(! in_array(strtolower((string) $correct), ['true', 'false'], true), ValidationException::withMessages(['options' => 'Select True or False as the correct answer.']));
            $options = [['option_text' => 'True', 'is_correct' => strtolower($correct) === 'true'], ['option_text' => 'False', 'is_correct' => strtolower($correct) === 'false']];
        }
        if ($type === 'short_answer') {
            $options = [];
        }
        if (in_array($type, ['multiple_choice', 'multiple_selection'], true) && count($options) < 2) {
            throw ValidationException::withMessages(['options' => 'At least two options are required.']);
        }
        $correct = collect($options)->where('is_correct', true)->count();
        if (in_array($type, ['multiple_choice', 'true_false'], true) && $correct !== 1) {
            throw ValidationException::withMessages(['options' => 'Exactly one correct answer is required.']);
        } if ($type === 'multiple_selection' && $correct < 1) {
            throw ValidationException::withMessages(['options' => 'Select at least one correct answer.']);
        }
        unset($data['options']);

        return [$data, collect($options)->values()->map(fn ($o, $i) => [...$o, 'display_order' => $i + 1])->all()];
    }

    private function log(Request $request, string $action, AssessmentBankQuestion $question): void
    {
        DB::table('assessment_activity_logs')->insert(['actor_id' => $request->user()->id, 'action' => $action, 'target_type' => AssessmentBankQuestion::class, 'target_id' => $question->id, 'metadata' => null, 'created_at' => now()]);
    }
}
