<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentTrainingAttachment;
use App\Models\TrainingLibraryMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssessmentTrainingAttachmentController extends Controller
{
    public function store(Request $request, Assessment $assessment): RedirectResponse
    {
        $d = $request->validate(['material_ids' => ['required', 'array', 'min:1'], 'material_ids.*' => ['integer', 'distinct', Rule::exists('training_library_materials', 'id')->where('status', 'active')], 'is_required' => ['required', 'boolean'], 'required_completion_percentage' => ['required', 'integer', 'between:0,100']]);
        $assessment->load('campaigns:id');
        if (! $assessment->applies_to_all_campaigns) {
            $compatible = TrainingLibraryMaterial::query()->whereIn('id', $d['material_ids'])->where(fn ($scope) => $scope->where('applies_to_all_campaigns', true)->orWhereHas('campaigns', fn ($c) => $c->whereIn('campaigns.id', $assessment->campaigns->modelKeys())))->count();
            abort_if($compatible !== count($d['material_ids']), 422, 'One or more training materials are outside this assessment campaign scope.');
        }
        DB::transaction(function () use ($assessment, $d) {
            $order = (int) $assessment->trainingAttachments()->max('display_order');
            foreach ($d['material_ids'] as $id) {
                $assessment->trainingAttachments()->firstOrCreate(['library_material_id' => $id], ['is_required' => $d['is_required'], 'required_completion_percentage' => $d['required_completion_percentage'], 'display_order' => ++$order]);
            }
        });

        return to_route('assessments.builder', $assessment)->with('status', count($d['material_ids']).' training material(s) added.');
    }

    public function destroy(Assessment $assessment, AssessmentTrainingAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->assessment_id === $assessment->id, 404);
        abort_if($attachment->progress()->exists() || $assessment->attempts()->exists(), 422, 'Attachment has employee history and cannot be removed.');
        $attachment->delete();

        return back();
    }
}
