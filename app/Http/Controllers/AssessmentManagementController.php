<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAssessmentRequest;
use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\Campaign;
use App\Services\AssessmentReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentManagementController extends Controller
{
    private const TIMEZONE = 'Asia/Manila';

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $category = $request->integer('category');
        $from = $request->date('from');
        $to = $request->date('to');
        $campaign = $request->integer('campaign');

        $assessments = Assessment::query()
            ->select(['id', 'category_id', 'title', 'description', 'instructions', 'difficulty', 'passing_score', 'time_limit_minutes', 'maximum_attempts', 'available_at', 'due_at', 'publish_at', 'allow_retake', 'randomize_questions', 'randomize_answers', 'show_correct_answers', 'applies_to_all_campaigns', 'status', 'simple_builder_enabled', 'planned_question_count', 'planned_total_points', 'created_by', 'updated_at'])
            ->with(['category:id,name', 'creator:id,name', 'campaigns:id,name,abbreviation'])
            ->withCount([
                'assignments',
                'assignments as assigned_count' => fn ($query) => $query->where('status', 'assigned'),
                'assignments as in_progress_count' => fn ($query) => $query->where('status', 'in_progress'),
                'assignments as pending_review_count' => fn ($query) => $query->where('status', 'pending_review'),
                'assignments as passed_count' => fn ($query) => $query->where('status', 'passed'),
                'assignments as failed_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->when(in_array($status, ['draft', 'published', 'archived'], true), fn ($query) => $query->where('status', $status), fn ($query) => $query->where('status', '!=', 'archived'))
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($category, fn ($query) => $query->where('category_id', $category))
            ->when($campaign, fn ($query) => $query->where(fn ($scope) => $scope->where('applies_to_all_campaigns', true)->orWhereHas('campaigns', fn ($c) => $c->whereKey($campaign))))
            ->when($from, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($to, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('assessments/management', [
            'assessments' => $assessments,
            'categories' => AssessmentCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'campaigns' => Campaign::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'filters' => ['status' => $status, 'search' => $search, 'category' => $category ?: '', 'campaign' => $campaign ?: '', 'from' => $request->string('from')->toString(), 'to' => $request->string('to')->toString()],
        ]);
    }

    public function schedule(Request $request): Response
    {
        $from = $request->date('from')?->startOfDay() ?? now(self::TIMEZONE)->startOfDay();
        $to = $request->date('to')?->endOfDay() ?? $from->copy()->addDays(30)->endOfDay();
        $assessments = Assessment::query()->select(['id', 'title', 'status', 'publish_at', 'available_at', 'due_at'])
            ->where(fn ($query) => $query->whereBetween('publish_at', [$from->copy()->utc(), $to->copy()->utc()])->orWhereBetween('available_at', [$from->copy()->utc(), $to->copy()->utc()])->orWhereBetween('due_at', [$from->copy()->utc(), $to->copy()->utc()]))
            ->orderByRaw('coalesce(available_at, publish_at, due_at)')->paginate(25)->withQueryString();

        return Inertia::render('assessments/schedule', ['assessments' => $assessments, 'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()]]);
    }

    public function store(SaveAssessmentRequest $request): RedirectResponse
    {
        $assessment = Assessment::query()->create([
            ...collect($request->validated())->except('campaign_ids')->all(),
            'status' => 'draft',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $assessment->campaigns()->sync($assessment->applies_to_all_campaigns ? [] : $request->validated('campaign_ids', []));
        $this->log($request, $assessment->publish_at ? 'Assessment Scheduled' : 'assessment.created', $assessment);

        return to_route('assessments.manage')->with('status', 'Assessment draft created.');
    }

    public function update(SaveAssessmentRequest $request, Assessment $assessment): RedirectResponse
    {
        abort_if($assessment->status === 'archived', 422, 'Archived assessments cannot be edited.');
        $assessment->update([...collect($request->validated())->except('campaign_ids')->all(), 'updated_by' => $request->user()->id]);
        $assessment->campaigns()->sync($assessment->applies_to_all_campaigns ? [] : $request->validated('campaign_ids', []));
        $this->log($request, $assessment->publish_at ? 'Assessment Scheduled' : 'assessment.edited', $assessment);

        return to_route('assessments.manage')->with('status', 'Assessment updated.');
    }

    public function clone(Request $request, Assessment $assessment): RedirectResponse
    {
        $clone = DB::transaction(function () use ($request, $assessment): Assessment {
            $assessment->load(['campaigns:id', 'questions' => fn ($query) => $query->whereNull('generated_for_attempt_id')->with('options'), 'trainingMaterials', 'trainingAttachments', 'randomPools']);
            $clone = $assessment->replicate(['status', 'published_at', 'publish_at', 'created_by', 'updated_by']);
            $clone->title = 'Copy of '.$assessment->title;
            $clone->status = 'draft';
            $clone->published_at = null;
            $clone->publish_at = null;
            $clone->created_by = $request->user()->id;
            $clone->updated_by = $request->user()->id;
            $clone->save();
            $clone->campaigns()->sync($assessment->campaigns->modelKeys());

            foreach ($assessment->questions as $question) {
                $questionClone = $question->replicate();
                $questionClone->assessment_id = $clone->id;
                $questionClone->save();
                $questionClone->options()->createMany($question->options->map(fn ($option) => $option->only(['option_text', 'is_correct', 'display_order']))->all());
            }
            foreach ($assessment->trainingMaterials as $material) {
                $materialClone = $material->replicate();
                $materialClone->assessment_id = $clone->id;
                $materialClone->created_by = $request->user()->id;
                $materialClone->save();
            }
            foreach ($assessment->trainingAttachments as $attachment) {
                $attachmentClone = $attachment->replicate();
                $attachmentClone->assessment_id = $clone->id;
                $attachmentClone->save();
            }
            foreach ($assessment->randomPools as $pool) {
                $poolClone = $pool->replicate();
                $poolClone->assessment_id = $clone->id;
                $poolClone->save();
            }
            DB::table('assessment_activity_logs')->insert([
                'actor_id' => $request->user()->id, 'action' => 'Assessment Cloned',
                'target_type' => Assessment::class, 'target_id' => $clone->id,
                'metadata' => json_encode(['source_assessment_id' => $assessment->id, 'new_assessment_id' => $clone->id], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return $clone;
        });

        $route = $clone->simple_builder_enabled ? 'assessments.simple.builder' : 'assessments.builder';

        return redirect()->route($route, $clone)->with('status', 'Assessment cloned as a new draft.');
    }

    public function preview(Assessment $assessment): Response
    {
        $assessment->load([
            'category:id,name',
            'trainingMaterials' => fn ($query) => $query->where('is_active', true)->orderBy('display_order')->select(['id', 'assessment_id', 'title', 'description', 'type', 'content', 'original_filename', 'mime_type', 'is_required', 'required_completion_percentage']),
            'questions' => fn ($query) => $query->with(['skill:id,name', 'options:id,question_id,option_text,is_correct,display_order'])->orderBy('display_order'),
        ]);

        return Inertia::render('assessments/preview', [
            'assessment' => $assessment,
        ]);
    }

    public function publish(Request $request, Assessment $assessment, AssessmentReadinessService $readiness): RedirectResponse
    {
        abort_if($assessment->status === 'archived', 422, 'Archived assessments cannot be published.');
        if ($assessment->simple_builder_enabled && ($errors = $readiness->errors($assessment)) !== []) {
            return to_route('assessments.simple.review', $assessment)->withErrors(['publish' => $errors]);
        }
        DB::transaction(function () use ($request, $assessment): void {
            $assessment->update(['status' => 'published', 'published_at' => now(), 'publish_at' => null, 'updated_by' => $request->user()->id]);
            $this->log($request, 'assessment.published', $assessment);
        });

        return to_route('assessments.manage')->with('status', 'Assessment published.');
    }

    public function archive(Request $request, Assessment $assessment): RedirectResponse
    {
        DB::transaction(function () use ($request, $assessment): void {
            $assessment->update(['status' => 'archived', 'updated_by' => $request->user()->id]);
            $this->log($request, 'assessment.archived', $assessment);
        });

        return to_route('assessments.manage')->with('status', 'Assessment archived.');
    }

    private function log(Request $request, string $action, Assessment $assessment): void
    {
        DB::table('assessment_activity_logs')->insert([
            'actor_id' => $request->user()->id,
            'action' => $action,
            'target_type' => Assessment::class,
            'target_id' => $assessment->id,
            'metadata' => json_encode(['title' => $assessment->title], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
