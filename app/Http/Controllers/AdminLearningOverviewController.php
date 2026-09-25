<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\CoachingRecord;
use App\Models\TrainingLibraryMaterial;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminLearningOverviewController extends Controller
{
    public function assessments(Request $request): Response
    {
        abort_unless($request->user()->role === 'admin', 403);
        $search = $this->search($request);
        $rows = Assessment::query()->select(['id', 'title', 'category_id', 'status'])
            ->with('category:id,name')
            ->withCount(['assignments', 'assignments as completed_count' => fn ($q) => $q->whereIn('status', ['passed', 'failed']), 'assignments as pending_count' => fn ($q) => $q->where('status', 'pending_review')])
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->latest('id')->paginate(15)->withQueryString()
            ->through(fn ($assessment) => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'subtitle' => $assessment->category?->name ?? 'General',
                'status' => $assessment->status,
                'details' => [$assessment->assignments_count.' assigned', $assessment->completed_count.' graded', $assessment->pending_count.' pending review'],
                'links' => [
                    ['label' => 'Preview', 'href' => route('assessments.preview', $assessment)],
                    ['label' => 'Employees & progress', 'href' => route('assessments.assignments', ['assessment_id' => $assessment->id])],
                    ['label' => 'Analytics', 'href' => route('assessments.reports.analytics', $assessment)],
                ],
            ]);

        return Inertia::render('admin/learning-overview', [
            'kind' => 'assessments', 'rows' => $rows, 'search' => $search,
            'summary' => [
                'All assessments' => Assessment::query()->count(),
                'Published' => Assessment::query()->where('status', 'published')->count(),
                'Employee assignments' => AssessmentAssignment::query()->count(),
                'Graded assignments' => AssessmentAssignment::query()->whereIn('status', ['passed', 'failed'])->count(),
                'Pending review' => AssessmentAssignment::query()->where('status', 'pending_review')->count(),
                'Library materials' => TrainingLibraryMaterial::query()->count(),
            ],
        ]);
    }

    public function coaching(Request $request): Response
    {
        abort_unless($request->user()->role === 'admin', 403);
        $search = $this->search($request);
        $like = '%'.addcslashes($search, '%_\\').'%';
        $rows = CoachingRecord::query()
            ->with(['employee:id,name,username', 'coach:id,name'])->withCount('trainingAssignments')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('employee', fn ($q) => $q->where('name', 'like', $like)->orWhere('username', 'like', $like))
                ->orWhereHas('coach', fn ($q) => $q->where('name', 'like', $like))
                ->orWhere('campaign_name', 'like', $like)->orWhere('team_name', 'like', $like)))
            ->latest('coaching_date')->latest('id')->paginate(15)->withQueryString()
            ->through(fn ($record) => [
                'id' => $record->id,
                'title' => $record->employee->name,
                'subtitle' => implode(' · ', array_filter([$record->employee->username, $record->campaign_name, $record->team_name])),
                'status' => $record->status,
                'details' => [
                    'Coach: '.$record->coach->name,
                    $record->type.' · '.$record->coaching_date->format('M j, Y'),
                    $record->training_assignments_count.' training items',
                    'Follow-up: '.($record->follow_up_date?->format('M j, Y') ?? 'None'),
                    $record->acknowledged_at ? 'Acknowledged' : 'Awaiting acknowledgment',
                ],
                'links' => [['label' => 'View coaching', 'href' => route('coaching.manage.show', $record)]],
            ]);

        return Inertia::render('admin/learning-overview', [
            'kind' => 'coaching', 'rows' => $rows, 'search' => $search,
            'summary' => [
                'All coaching records' => CoachingRecord::query()->count(),
                'Employees coached' => CoachingRecord::query()->distinct()->count('employee_id'),
                'Open' => CoachingRecord::query()->where('status', 'open')->count(),
                'Follow-up required' => CoachingRecord::query()->where('status', 'follow_up_required')->count(),
                'Completed' => CoachingRecord::query()->where('status', 'completed')->count(),
                'Overdue follow-ups' => CoachingRecord::query()->where('status', '!=', 'completed')->whereDate('follow_up_date', '<', today())->count(),
            ],
        ]);
    }

    private function search(Request $request): string
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        return trim($data['search'] ?? '');
    }
}
