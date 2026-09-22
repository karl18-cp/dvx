<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentCategory;
use App\Models\AssessmentSkill;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentBuilderController extends Controller
{
    public function edit(Assessment $assessment): Response
    {
        $assessment->load([
            'category:id,name',
            'trainingMaterials' => fn ($query) => $query->where('is_active', true)->orderBy('display_order')->select(['id', 'assessment_id', 'title', 'description', 'type', 'content', 'original_filename', 'mime_type', 'file_size', 'is_required', 'required_completion_percentage', 'display_order']),
            'trainingAttachments' => fn ($query) => $query->whereHas('material', fn ($material) => $material->where('status', 'active'))->with(['material:id,title,description,type,content,original_filename,mime_type,file_size,status'])->orderBy('display_order'),
            'questions' => fn ($query) => $query->whereNull('generated_for_attempt_id')->with(['skill:id,name', 'options:id,question_id,option_text,is_correct,display_order'])->orderBy('display_order'),
            'randomPools',
        ]);

        return Inertia::render('assessments/builder', [
            'assessment' => $assessment,
            'categories' => AssessmentCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'skills' => AssessmentSkill::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'totals' => [
                'questions' => $assessment->questions->count() + $assessment->randomPools->sum('questions_to_select'),
                'points' => $assessment->questions->sum('points'),
                'materials' => $assessment->trainingMaterials->count() + $assessment->trainingAttachments->count(),
            ],
        ]);
    }
}
