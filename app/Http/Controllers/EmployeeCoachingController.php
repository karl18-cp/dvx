<?php

namespace App\Http\Controllers;

use App\Models\CoachingRecord;
use App\Models\CoachingTrainingAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeCoachingController extends Controller
{
    public function index(Request $request, AdminLearningOverviewController $overview): Response
    {
        if ($request->user()->role === 'admin') {
            return $overview->coaching($request);
        }

        $records = CoachingRecord::query()->where('employee_id', $request->user()->id)->with(['coach:id,name', 'skill:id,name', 'assessment:id,title'])->withCount('trainingAssignments')->latest('coaching_date')->paginate(12);

        return Inertia::render('coaching/my-coaching', ['records' => $records]);
    }

    public function show(Request $request, CoachingRecord $coaching): Response
    {
        $this->authorizeEmployee($request, $coaching);
        $coaching->load(['coach:id,name', 'skill:id,name', 'assessment:id,title', 'trainingAssignments.material.skill:id,name', 'trainingAssignments.progress' => fn ($q) => $q->where('employee_id', $request->user()->id)]);

        return Inertia::render('coaching/my-coaching-show', ['coaching' => $coaching]);
    }

    public function acknowledge(Request $request, CoachingRecord $coaching): RedirectResponse
    {
        $this->authorizeEmployee($request, $coaching);
        if (! $coaching->acknowledged_at) {
            DB::transaction(function () use ($request, $coaching): void {
                $coaching->update(['acknowledged_at' => now()]);
                DB::table('assessment_activity_logs')->insert(['actor_id' => $request->user()->id, 'action' => 'Coaching Acknowledged', 'target_type' => CoachingRecord::class, 'target_id' => $coaching->id, 'metadata' => null, 'created_at' => now()]);
            });
        }

        return back()->with('status', 'Coaching acknowledged.');
    }

    public function open(Request $request, CoachingRecord $coaching, CoachingTrainingAssignment $assignment): RedirectResponse
    {
        $this->authorizeAssignment($request, $coaching, $assignment);
        $assignment->progress()->firstOrCreate(['employee_id' => $request->user()->id], ['started_at' => now()]);

        return back();
    }

    public function complete(Request $request, CoachingRecord $coaching, CoachingTrainingAssignment $assignment): RedirectResponse
    {
        $this->authorizeAssignment($request, $coaching, $assignment);
        $progress = $assignment->progress()->firstOrCreate(['employee_id' => $request->user()->id], ['started_at' => now()]);
        $progress->update(['completion_percentage' => 100, 'completed_at' => $progress->completed_at ?? now()]);
        DB::table('assessment_activity_logs')->insert(['actor_id' => $request->user()->id, 'action' => 'Coaching Training Completed', 'target_type' => CoachingTrainingAssignment::class, 'target_id' => $assignment->id, 'metadata' => json_encode(['coaching_id' => $coaching->id]), 'created_at' => now()]);

        return back()->with('status', 'Training marked as reviewed.');
    }

    public function media(Request $request, CoachingRecord $coaching, CoachingTrainingAssignment $assignment): StreamedResponse
    {
        $this->authorizeAssignment($request, $coaching, $assignment);
        $material = $assignment->material;
        abort_unless($material->storage_disk && $material->storage_key, 404);
        $disk = Storage::disk($material->storage_disk);
        abort_unless($disk->exists($material->storage_key), 404);

        return response()->streamDownload(fn () => fpassthru($disk->readStream($material->storage_key)), $material->original_filename, ['Content-Type' => $material->mime_type, 'Content-Disposition' => 'inline']);
    }

    private function authorizeEmployee(Request $request, CoachingRecord $coaching): void
    {
        abort_unless($coaching->employee_id === $request->user()->id, 403);
    }

    private function authorizeAssignment(Request $request, CoachingRecord $coaching, CoachingTrainingAssignment $assignment): void
    {
        $this->authorizeEmployee($request, $coaching);
        abort_unless($assignment->coaching_record_id === $coaching->id, 404);
    }
}
