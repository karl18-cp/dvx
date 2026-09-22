<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCallEvaluationScorecardRequest;
use App\Models\AssessmentSkill;
use App\Models\CallEvaluationScorecard;
use App\Models\Campaign;
use App\Support\CallEvaluationScorecardValidator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CallEvaluationScorecardController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $campaign = $request->integer('campaign');
        $escapedSearch = addcslashes($search, '%_\\');
        $scorecards = CallEvaluationScorecard::query()
            ->select(['id', 'name', 'description', 'applies_to_all_campaigns', 'status', 'passing_score', 'updated_at'])
            ->with('campaigns:id,name,abbreviation')->withCount(['categories', 'criteria'])
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$escapedSearch}%")->orWhere('description', 'like', "%{$escapedSearch}%")))
            ->when(in_array($status, ['draft', 'active', 'archived'], true), fn ($query) => $query->where('status', $status))
            ->when($campaign, fn ($query) => $query->where(fn ($nested) => $nested->where('applies_to_all_campaigns', true)->orWhereHas('campaigns', fn ($campaigns) => $campaigns->whereKey($campaign))))
            ->latest('updated_at')->paginate(15)->withQueryString();

        return Inertia::render('quality/scorecards/index', ['scorecards' => $scorecards, 'campaigns' => $this->campaigns(), 'filters' => compact('search', 'status', 'campaign')]);
    }

    public function store(SaveCallEvaluationScorecardRequest $request): RedirectResponse
    {
        $scorecard = DB::transaction(function () use ($request): CallEvaluationScorecard {
            $data = $request->validated();
            $campaignIds = $data['campaign_ids'] ?? [];
            unset($data['campaign_ids']);
            $scorecard = CallEvaluationScorecard::query()->create([...$data, 'status' => 'draft', 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            $scorecard->campaigns()->sync($scorecard->applies_to_all_campaigns ? [] : $campaignIds);
            $this->log($request, 'QA Scorecard Created', $scorecard);

            return $scorecard;
        });

        return to_route('quality.scorecards.builder', $scorecard)->with('status', 'QA Scorecard draft created.');
    }

    public function builder(CallEvaluationScorecard $scorecard): Response
    {
        $scorecard->load(['campaigns:id,name,abbreviation', 'categories.criteria.skill:id,name']);

        return Inertia::render('quality/scorecards/builder', ['scorecard' => $scorecard, 'campaigns' => $this->campaigns(), 'skills' => AssessmentSkill::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']), 'total_points' => $scorecard->criteria->sum(fn ($criterion) => (float) $criterion->points_possible)]);
    }

    public function update(SaveCallEvaluationScorecardRequest $request, CallEvaluationScorecard $scorecard): RedirectResponse
    {
        abort_if($scorecard->status === 'archived', 422, 'Archived Scorecards are read-only. Duplicate one to make changes.');
        DB::transaction(function () use ($request, $scorecard): void {
            $data = $request->validated();
            $campaignIds = $data['campaign_ids'] ?? [];
            unset($data['campaign_ids']);
            $scorecard->update([...$data, 'updated_by' => $request->user()->id]);
            $scorecard->campaigns()->sync($scorecard->applies_to_all_campaigns ? [] : $campaignIds);
            $this->log($request, 'QA Scorecard Updated', $scorecard);
        });

        return back()->with('status', 'Scorecard settings saved.');
    }

    public function preview(CallEvaluationScorecard $scorecard): Response
    {
        $scorecard->load(['campaigns:id,name,abbreviation', 'categories.criteria.skill:id,name']);

        return Inertia::render('quality/scorecards/preview', ['scorecard' => $scorecard, 'total_points' => $scorecard->criteria->sum(fn ($criterion) => (float) $criterion->points_possible)]);
    }

    public function activate(Request $request, CallEvaluationScorecard $scorecard, CallEvaluationScorecardValidator $validator): RedirectResponse
    {
        abort_unless($scorecard->status === 'draft', 422, 'Only Draft Scorecards can be activated.');
        $errors = $validator->activationErrors($scorecard);
        if ($errors !== []) {
            throw ValidationException::withMessages(['activation' => $errors]);
        }
        DB::transaction(function () use ($request, $scorecard): void {
            $scorecard->update(['status' => 'active', 'archived_at' => null, 'updated_by' => $request->user()->id]);
            $this->log($request, 'QA Scorecard Activated', $scorecard);
        });

        return back()->with('status', 'QA Scorecard activated.');
    }

    public function archive(Request $request, CallEvaluationScorecard $scorecard): RedirectResponse
    {
        if ($scorecard->status !== 'archived') {
            DB::transaction(function () use ($request, $scorecard): void {
                $scorecard->update(['status' => 'archived', 'archived_at' => now(), 'updated_by' => $request->user()->id]);
                $this->log($request, 'QA Scorecard Archived', $scorecard);
            });
        }

        return back()->with('status', 'QA Scorecard archived.');
    }

    public function duplicate(Request $request, CallEvaluationScorecard $scorecard): RedirectResponse
    {
        $clone = DB::transaction(function () use ($request, $scorecard): CallEvaluationScorecard {
            $scorecard->load(['campaigns:id', 'categories.criteria']);
            $clone = $scorecard->replicate(['status', 'archived_at', 'created_by', 'updated_by']);
            $clone->forceFill(['name' => 'Copy of '.$scorecard->name, 'status' => 'draft', 'archived_at' => null, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id])->save();
            $clone->campaigns()->sync($scorecard->campaigns->modelKeys());
            foreach ($scorecard->categories as $category) {
                $categoryClone = $category->replicate();
                $categoryClone->scorecard_id = $clone->id;
                $categoryClone->save();
                foreach ($category->criteria as $criterion) {
                    $criterionClone = $criterion->replicate();
                    $criterionClone->category_id = $categoryClone->id;
                    $criterionClone->save();
                }
            }
            $this->log($request, 'QA Scorecard Duplicated', $clone, ['source_scorecard_id' => $scorecard->id]);

            return $clone;
        });

        return to_route('quality.scorecards.builder', $clone)->with('status', 'Scorecard duplicated as Draft.');
    }

    public function destroy(Request $request, CallEvaluationScorecard $scorecard): RedirectResponse
    {
        abort_unless($scorecard->status === 'draft', 422, 'Only unused Draft Scorecards can be deleted.');
        abort_if($scorecard->evaluations()->exists(), 422, 'Used Scorecards must be archived.');
        DB::transaction(function () use ($request, $scorecard): void {
            $this->log($request, 'QA Scorecard Draft Deleted', $scorecard);
            $scorecard->delete();
        });

        return to_route('quality.scorecards.index')->with('status', 'Draft deleted.');
    }

    /** @return Collection<int, Campaign> */
    private function campaigns(): Collection
    {
        return Campaign::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']);
    }

    private function log(Request $request, string $action, CallEvaluationScorecard $scorecard, array $metadata = []): void
    {
        DB::table('assessment_activity_logs')->insert(['actor_id' => $request->user()->id, 'action' => $action, 'target_type' => CallEvaluationScorecard::class, 'target_id' => $scorecard->id, 'metadata' => json_encode(['name' => $scorecard->name, ...$metadata], JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
