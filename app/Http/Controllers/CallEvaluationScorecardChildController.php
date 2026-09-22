<?php

namespace App\Http\Controllers;

use App\Models\CallEvaluationScorecard;
use App\Models\CallEvaluationScorecardCategory;
use App\Models\CallEvaluationScorecardCriterion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CallEvaluationScorecardChildController extends Controller
{
    public function storeCategory(Request $request, CallEvaluationScorecard $scorecard): RedirectResponse
    {
        $this->writable($scorecard);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000']]);
        $scorecard->categories()->create([...$data, 'display_order' => $scorecard->categories()->max('display_order') + 1]);

        return back();
    }

    public function updateCategory(Request $request, CallEvaluationScorecard $scorecard, CallEvaluationScorecardCategory $category): RedirectResponse
    {
        $this->category($scorecard, $category);
        $category->update($request->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000']]));

        return back();
    }

    public function deleteCategory(CallEvaluationScorecard $scorecard, CallEvaluationScorecardCategory $category): RedirectResponse
    {
        $this->category($scorecard, $category);
        DB::transaction(fn () => [$category->delete(), $this->normalizeCategories($scorecard)]);

        return back();
    }

    public function moveCategory(Request $request, CallEvaluationScorecard $scorecard, CallEvaluationScorecardCategory $category): RedirectResponse
    {
        $this->category($scorecard, $category);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];
        $this->move($scorecard->categories(), $category, $direction);

        return back();
    }

    public function storeCriterion(Request $request, CallEvaluationScorecard $scorecard, CallEvaluationScorecardCategory $category): RedirectResponse
    {
        $this->category($scorecard, $category);
        $data = $this->criterionData($request);
        $category->criteria()->create([...$data, 'display_order' => $category->criteria()->max('display_order') + 1]);

        return back();
    }

    public function updateCriterion(Request $request, CallEvaluationScorecard $scorecard, CallEvaluationScorecardCategory $category, CallEvaluationScorecardCriterion $criterion): RedirectResponse
    {
        $this->criterion($scorecard, $category, $criterion);
        $criterion->update($this->criterionData($request));

        return back();
    }

    public function deleteCriterion(CallEvaluationScorecard $scorecard, CallEvaluationScorecardCategory $category, CallEvaluationScorecardCriterion $criterion): RedirectResponse
    {
        $this->criterion($scorecard, $category, $criterion);
        DB::transaction(fn () => [$criterion->delete(), $this->normalizeCriteria($category)]);

        return back();
    }

    public function moveCriterion(Request $request, CallEvaluationScorecard $scorecard, CallEvaluationScorecardCategory $category, CallEvaluationScorecardCriterion $criterion): RedirectResponse
    {
        $this->criterion($scorecard, $category, $criterion);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];
        $this->move($category->criteria(), $criterion, $direction);

        return back();
    }

    private function criterionData(Request $request): array
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:255'], 'guidance' => ['nullable', 'string', 'max:5000'], 'points_possible' => ['required', 'numeric', 'gt:0', 'max:999999.99'], 'skill_id' => ['nullable', 'integer', Rule::exists('assessment_skills', 'id')->where('is_active', true)], 'is_required' => ['required', 'boolean'], 'is_critical' => ['required', 'boolean'], 'allows_na' => ['required', 'boolean']]);
        if ($data['is_critical'] && ! $request->has('allows_na')) {
            $data['allows_na'] = false;
        }

        return $data;
    }

    private function writable(CallEvaluationScorecard $s): void
    {
        abort_if($s->status === 'archived', 422, 'Archived Scorecards are read-only.');
    }

    private function category(CallEvaluationScorecard $s, CallEvaluationScorecardCategory $c): void
    {
        $this->writable($s);
        abort_unless($c->scorecard_id === $s->id, 404);
    }

    private function criterion(CallEvaluationScorecard $s, CallEvaluationScorecardCategory $c, CallEvaluationScorecardCriterion $x): void
    {
        $this->category($s, $c);
        abort_unless($x->category_id === $c->id, 404);
    }

    private function move($query, $item, string $direction): void
    {
        DB::transaction(function () use ($query, $item, $direction) {
            $rows = $query->get()->values();
            $i = $rows->search(fn ($x) => $x->id === $item->id);
            $j = $direction === 'up' ? $i - 1 : $i + 1;
            if ($j < 0 || $j >= $rows->count()) {
                return;
            }[$rows[$i],$rows[$j]] = [$rows[$j], $rows[$i]];
            foreach ($rows as $k => $row) {
                $row->update(['display_order' => $k + 1]);
            }
        });
    }

    private function normalizeCategories(CallEvaluationScorecard $s): void
    {
        foreach ($s->categories()->get() as $i => $row) {
            $row->update(['display_order' => $i + 1]);
        }
    }

    private function normalizeCriteria(CallEvaluationScorecardCategory $c): void
    {
        foreach ($c->criteria()->get() as $i => $row) {
            $row->update(['display_order' => $i + 1]);
        }
    }
}
