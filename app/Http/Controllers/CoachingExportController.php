<?php

namespace App\Http\Controllers;

use App\Models\CoachingRecord;
use App\Services\QaAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CoachingExportController extends Controller
{
    public function __invoke(Request $request, CoachingRecord $coaching, QaAccessService $access): JsonResponse
    {
        $access->authorizeTeam($request->user(), $coaching->team_id);
        $coaching->load(['employee', 'coach', 'skill', 'assessment', 'callEvaluation', 'trainingAssignments.material', 'trainingAssignments.progress']);
        $evaluation = $coaching->callEvaluation;
        if ($evaluation) {
            $access->authorizeTeam($request->user(), $evaluation->team_id);
            abort_unless($evaluation->status === 'submitted' || in_array($request->user()->role, ['admin', 'manager'], true), 404);
        }
        $lines = [
            'Coaching Log #'.$coaching->id,
            'Employee: '.$coaching->employee->name,
            'Username: '.$coaching->employee->username,
            'Campaign: '.$coaching->campaign_name,
            'Team: '.$coaching->team_name,
            'Coach: '.$coaching->coach->name,
            'Coaching date: '.$coaching->coaching_date?->format('Y-m-d'),
            'Type: '.$coaching->type,
            'Status: '.$coaching->status,
            'Skill: '.($coaching->skill?->name ?? 'General'),
            'Assessment: '.($coaching->assessment?->title ?? 'None'),
            'Follow-up: '.($coaching->follow_up_date?->format('Y-m-d') ?? 'None'),
            'Acknowledged: '.($coaching->acknowledged_at?->toIso8601String() ?? 'Not yet'),
            'Completed: '.($coaching->completed_at?->toIso8601String() ?? 'Not yet'),
            '', 'Summary', $coaching->summary,
            '', 'Strengths', $coaching->strengths ?? '',
            '', 'Areas for improvement', $coaching->areas_for_improvement ?? '',
            '', 'Action plan', $coaching->action_plan ?? '',
            '', 'Assigned training',
        ];
        foreach ($coaching->trainingAssignments as $assignment) {
            $completed = $assignment->progress->where('employee_id', $coaching->employee_id)->whereNotNull('completed_at')->isNotEmpty();
            $lines[] = ($assignment->material?->title ?? 'Unavailable material').' — '.($completed ? 'Completed' : 'Pending');
        }
        $attachments = [];
        $warnings = [];
        if ($evaluation) {
            $lines = [...$lines, '', 'Source Call Evaluation #'.$evaluation->id, 'Scorecard: '.$evaluation->scorecard_name, 'Evaluation status: '.$evaluation->status, 'Score: '.($evaluation->percentage ?? 'Not scored'), 'Call date: '.$evaluation->call_at?->toIso8601String(), 'Reference: '.$evaluation->call_reference];
            foreach ([
                ['recording', $evaluation->storage_disk, $evaluation->storage_key, $evaluation->original_filename],
                ['document', $evaluation->document_storage_disk, $evaluation->document_storage_key, $evaluation->document_original_filename],
            ] as [$kind, $disk, $key, $name]) {
                if (! $disk || ! $key || ! Storage::disk($disk)->exists($key)) {
                    $warnings[] = ucfirst($kind).' is not attached or is unavailable.';

                    continue;
                }
                $attachments[] = ['name' => $kind.'/'.basename(str_replace('\\', '/', $name ?: $kind)), 'url' => route('quality.evaluations.'.$kind, $evaluation), 'size' => Storage::disk($disk)->size($key)];
            }
        } else {
            $warnings[] = 'No linked call evaluation; no recording or original evaluation DOCX is attached.';
        }

        return response()->json(['filename' => 'coaching-log-'.$coaching->id.'.zip', 'lines' => $lines, 'attachments' => $attachments, 'warnings' => $warnings], 200, ['Cache-Control' => 'private, no-store']);
    }
}
