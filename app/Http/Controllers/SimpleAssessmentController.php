<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentBankQuestion;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSkill;
use App\Models\Campaign;
use App\Models\TrainingLibraryMaterial;
use App\Services\AssessmentMediaStorage;
use App\Services\AssessmentReadinessService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SimpleAssessmentController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('assessments/simple-create', [
            'campaigns' => Campaign::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'trainingMaterials' => TrainingLibraryMaterial::query()->where('status', 'active')->with('campaigns:id')->latest()->limit(25)->get(['id', 'title', 'type', 'applies_to_all_campaigns']),
        ]);
    }

    public function store(Request $request, AssessmentMediaStorage $storage): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
            'applies_to_all_campaigns' => ['required', 'boolean'], 'campaign_ids' => ['required_if:applies_to_all_campaigns,false', 'array'],
            'campaign_ids.*' => ['integer', 'distinct', Rule::exists('campaigns', 'id')->where('is_active', true)],
            'training_source' => ['required', Rule::in(['none', 'library', 'upload'])],
            'library_material_id' => ['nullable', 'required_if:training_source,library', Rule::exists('training_library_materials', 'id')->where('status', 'active')],
            'training_required' => ['required', 'boolean'], 'training_title' => ['nullable', 'required_if:training_source,upload', 'string', 'max:255'],
            'training_type' => ['nullable', 'required_if:training_source,upload', Rule::in(['video', 'audio', 'image', 'document'])],
            'training_file' => ['nullable', 'required_if:training_source,upload', 'file', 'max:'.config('assessments.max_upload_kilobytes')],
            'question_count' => ['required', 'integer', 'between:1,100'], 'total_points' => ['required', 'integer', 'between:1,100000'],
            'passing_score' => ['required', 'numeric', 'between:0,100'], 'time_limit_minutes' => ['nullable', 'integer', 'between:1,1440'],
            'maximum_attempts' => ['required', 'integer', 'between:1,3'],
        ]);
        if ($data['total_points'] < $data['question_count']) {
            throw ValidationException::withMessages(['total_points' => 'Total points must be at least the number of questions.']);
        }
        if ($data['training_source'] === 'library' && ! $data['applies_to_all_campaigns']) {
            $compatible = TrainingLibraryMaterial::query()->whereKey($data['library_material_id'])->where(fn ($scope) => $scope->where('applies_to_all_campaigns', true)->orWhereHas('campaigns', fn ($campaigns) => $campaigns->whereIn('campaigns.id', $data['campaign_ids'])))->exists();
            if (! $compatible) {
                throw ValidationException::withMessages(['library_material_id' => 'Select a Training Library item compatible with the assessment campaign.']);
            }
        }

        $metadata = [];
        if ($data['training_source'] === 'upload') {
            $metadata = $storage->store($request->file('training_file'), 0);
        }
        try {
            $assessment = DB::transaction(function () use ($data, $metadata, $request): Assessment {
                $assessment = Assessment::query()->create([
                    'title' => $data['title'], 'description' => $data['description'] ?? null, 'difficulty' => 'beginner',
                    'passing_score' => $data['passing_score'], 'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
                    'maximum_attempts' => $data['maximum_attempts'], 'allow_retake' => $data['maximum_attempts'] > 1,
                    'randomize_questions' => false, 'randomize_answers' => false, 'reduce_repeated_questions' => true,
                    'show_correct_answers' => false, 'applies_to_all_campaigns' => $data['applies_to_all_campaigns'],
                    'status' => 'draft', 'simple_builder_enabled' => true, 'planned_question_count' => $data['question_count'],
                    'planned_total_points' => $data['total_points'], 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
                ]);
                $assessment->campaigns()->sync($data['applies_to_all_campaigns'] ? [] : $data['campaign_ids']);
                if ($data['training_source'] === 'library') {
                    $assessment->trainingAttachments()->create(['library_material_id' => $data['library_material_id'], 'is_required' => $data['training_required'], 'required_completion_percentage' => 100, 'display_order' => 1]);
                } elseif ($data['training_source'] === 'upload') {
                    $assessment->trainingMaterials()->create([...$metadata, 'title' => $data['training_title'], 'type' => $data['training_type'], 'is_required' => $data['training_required'], 'required_completion_percentage' => 100, 'display_order' => 1, 'created_by' => $request->user()->id]);
                }
                foreach ($this->points($data['question_count'], $data['total_points']) as $index => $points) {
                    $assessment->questions()->create(['question_text' => '', 'question_type' => 'multiple_choice', 'points' => $points, 'display_order' => $index + 1, 'is_required' => true]);
                }
                DB::table('assessment_activity_logs')->insert(['actor_id' => $request->user()->id, 'action' => 'assessment.created', 'target_type' => Assessment::class, 'target_id' => $assessment->id, 'metadata' => json_encode(['title' => $assessment->title, 'simple_builder' => true]), 'created_at' => now()]);

                return $assessment;
            });
        } catch (\Throwable $error) {
            $storage->delete($metadata['storage_disk'] ?? null, $metadata['storage_key'] ?? null);
            throw $error;
        }

        return to_route('assessments.simple.builder', $assessment)->with('status', 'Draft created. Complete each question, then review and publish.');
    }

    public function builder(Assessment $assessment, AssessmentReadinessService $readiness): Response
    {
        abort_unless($assessment->simple_builder_enabled, 404);
        $assessment->load(['campaigns:id,name,abbreviation', 'questions' => fn ($q) => $q->whereNull('generated_for_attempt_id')->with(['skill:id,name', 'options'])->orderBy('display_order'), 'trainingMaterials' => fn ($q) => $q->where('is_active', true), 'trainingAttachments.material']);
        $questions = $assessment->questions->values()->map(fn ($question, $index) => [...$question->toArray(), 'ready' => $readiness->questionErrors($question, $index + 1) === []]);

        return Inertia::render('assessments/simple-builder', ['assessment' => [...$assessment->toArray(), 'questions' => $questions], 'skills' => AssessmentSkill::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']), 'reviewMode' => false, 'readinessErrors' => $readiness->errors($assessment)]);
    }

    public function review(Assessment $assessment, AssessmentReadinessService $readiness): Response
    {
        abort_unless($assessment->simple_builder_enabled, 404);
        $assessment->load(['campaigns:id,name,abbreviation', 'questions' => fn ($q) => $q->whereNull('generated_for_attempt_id')->with(['skill:id,name', 'options'])->orderBy('display_order'), 'trainingMaterials' => fn ($q) => $q->where('is_active', true), 'trainingAttachments.material']);
        $questions = $assessment->questions->values()->map(fn ($question, $index) => [...$question->toArray(), 'ready' => $readiness->questionErrors($question, $index + 1) === []]);

        return Inertia::render('assessments/simple-builder', ['assessment' => [...$assessment->toArray(), 'questions' => $questions], 'skills' => [], 'reviewMode' => true, 'readinessErrors' => $readiness->errors($assessment)]);
    }

    public function schedule(Assessment $assessment, AssessmentReadinessService $readiness): Response
    {
        abort_unless($assessment->simple_builder_enabled, 404);

        return Inertia::render('assessments/simple-schedule', [
            'assessment' => $assessment->only(['id', 'title', 'publish_at', 'available_at', 'due_at']),
            'readinessErrors' => $readiness->errors($assessment),
        ]);
    }

    public function saveSchedule(Request $request, Assessment $assessment, AssessmentReadinessService $readiness): RedirectResponse
    {
        abort_unless($assessment->simple_builder_enabled && $assessment->status === 'draft', 404);
        if (($errors = $readiness->errors($assessment)) !== []) {
            return to_route('assessments.simple.review', $assessment)->withErrors(['publish' => $errors]);
        }
        $data = $request->validate([
            'publish_at' => ['required', 'date', 'after:now'],
            'available_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:available_at'],
        ]);
        foreach (['publish_at', 'available_at', 'due_at'] as $field) {
            $data[$field] = isset($data[$field]) ? Carbon::parse($data[$field], 'Asia/Manila')->utc() : null;
        }
        $assessment->update([...$data, 'updated_by' => $request->user()->id]);
        DB::table('assessment_activity_logs')->insert(['actor_id' => $request->user()->id, 'action' => 'Assessment Scheduled', 'target_type' => Assessment::class, 'target_id' => $assessment->id, 'metadata' => json_encode(['publish_at' => $data['publish_at']]), 'created_at' => now()]);

        return to_route('assessments.manage')->with('status', 'Assessment scheduled.');
    }

    public function bank(Request $request, Assessment $assessment): Response
    {
        abort_unless($assessment->simple_builder_enabled, 404);
        $assessment->loadMissing('campaigns:id');
        $search = trim($request->string('search')->toString());
        $type = $request->string('type')->toString();

        $questions = AssessmentBankQuestion::query()->where('status', 'active')->with(['options', 'skill:id,name'])->when(in_array($type, ['multiple_choice', 'true_false', 'multiple_selection', 'short_answer'], true), fn ($q) => $q->where('question_type', $type))->when($search, fn ($q) => $q->where('question_text', 'like', '%'.addcslashes($search, '%_\\').'%'))->when(! $assessment->applies_to_all_campaigns, fn ($q) => $q->where(fn ($scope) => $scope->where('applies_to_all_campaigns', true)->orWhereHas('campaigns', fn ($campaigns) => $campaigns->whereIn('campaigns.id', $assessment->campaigns->modelKeys()))))->latest()->paginate(20)->withQueryString();

        return Inertia::render('assessments/simple-question-bank', [
            'assessment' => $assessment->only(['id', 'title']),
            'slot' => $request->integer('slot'),
            'availableSlots' => $assessment->questions()->whereNull('generated_for_attempt_id')->where(fn ($query) => $query->whereNull('question_text')->orWhere('question_text', ''))->count()
                + $assessment->questions()->whereKey($request->integer('slot'))->whereNot(fn ($query) => $query->whereNull('question_text')->orWhere('question_text', ''))->count(),
            'questions' => $questions,
            'search' => $search,
            'type' => $type,
        ]);
    }

    public function useBanks(Request $request, Assessment $assessment): RedirectResponse
    {
        abort_unless($assessment->simple_builder_enabled, 404);
        $data = $request->validate([
            'bank_question_ids' => ['required', 'array', 'min:1', 'max:100'],
            'bank_question_ids.*' => ['required', 'integer', 'distinct'],
            'starting_question_id' => ['nullable', 'integer'],
        ]);
        $assessment->loadMissing('campaigns:id');

        DB::transaction(function () use ($assessment, $data): void {
            $questions = $assessment->questions()->whereNull('generated_for_attempt_id')->orderBy('display_order')->lockForUpdate()->get();
            $starting = $questions->firstWhere('id', $data['starting_question_id'] ?? 0);
            abort_if(($data['starting_question_id'] ?? null) && ! $starting, 404);
            $slots = collect([$starting])->filter()->merge($questions->filter(fn ($question) => trim((string) $question->question_text) === '' && $question->id !== $starting?->id))->values();
            abort_if(count($data['bank_question_ids']) > $slots->count(), 422, "Only {$slots->count()} question slots are available.");
            $banks = AssessmentBankQuestion::query()->with(['options', 'campaigns:id'])->where('status', 'active')->whereIn('id', $data['bank_question_ids'])->get()->keyBy('id');
            abort_if($banks->count() !== count($data['bank_question_ids']), 422, 'One or more selected bank questions are unavailable.');

            foreach ($data['bank_question_ids'] as $index => $bankId) {
                $bank = $banks->get($bankId);
                $compatible = $bank->applies_to_all_campaigns || $assessment->applies_to_all_campaigns || $bank->campaigns->pluck('id')->intersect($assessment->campaigns->modelKeys())->isNotEmpty();
                abort_unless($compatible, 422, "Question '{$bank->question_text}' does not match the assessment campaign.");
                $question = $slots[$index];
                $question->update(['skill_id' => $bank->skill_id, 'source_bank_question_id' => $bank->id, 'question_text' => $bank->question_text, 'question_type' => $bank->question_type, 'feedback' => $bank->feedback]);
                $question->options()->delete();
                $question->options()->createMany($bank->options->map(fn ($option) => $option->only(['option_text', 'is_correct', 'display_order']))->all());
            }
        });

        return to_route('assessments.simple.builder', $assessment)->with('status', count($data['bank_question_ids']).' questions added from the Question Bank.');
    }

    public function useBank(Request $request, Assessment $assessment, AssessmentQuestion $question): RedirectResponse
    {
        abort_unless($assessment->simple_builder_enabled && $question->assessment_id === $assessment->id, 404);
        $bank = AssessmentBankQuestion::query()->with('options')->where('status', 'active')->findOrFail($request->validate(['bank_question_id' => ['required', 'integer']])['bank_question_id']);
        $assessment->loadMissing('campaigns:id');
        abort_unless($bank->applies_to_all_campaigns || $assessment->applies_to_all_campaigns || $bank->campaigns()->whereIn('campaigns.id', $assessment->campaigns->modelKeys())->exists(), 422, 'This bank question does not match the assessment campaign.');
        DB::transaction(function () use ($question, $bank): void {
            $question->update(['skill_id' => $bank->skill_id, 'source_bank_question_id' => $bank->id, 'question_text' => $bank->question_text, 'question_type' => $bank->question_type, 'feedback' => $bank->feedback]);
            $question->options()->delete();
            $question->options()->createMany($bank->options->map(fn ($option) => $option->only(['option_text', 'is_correct', 'display_order']))->all());
        });

        return back()->with('status', 'Question slot populated from the Question Bank.');
    }

    /** @return list<int> */
    private function points(int $questions, int $total): array
    {
        $base = intdiv($total, $questions);
        $remainder = $total % $questions;

        return array_map(fn ($index) => $base + ($index < $remainder ? 1 : 0), range(0, $questions - 1));
    }
}
