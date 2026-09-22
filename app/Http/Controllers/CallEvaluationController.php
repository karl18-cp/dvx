<?php

namespace App\Http\Controllers;

use App\Models\CallEvaluation;
use App\Models\CallEvaluationCriterionResult;
use App\Models\CallEvaluationScorecard;
use App\Models\Campaign;
use App\Models\Team;
use App\Models\User;
use App\Services\CallEvaluationScoringService;
use App\Services\CallEvaluationSnapshotService;
use App\Services\EvaluationCoachingService;
use App\Services\QaAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CallEvaluationController extends Controller
{
    public function index(Request $request, QaAccessService $access): Response
    {
        $filters = $request->only(['employee', 'campaign', 'team', 'scorecard', 'evaluator', 'status', 'result', 'from', 'to']);
        $query = CallEvaluation::query()->with(['employee:id,name,username', 'evaluator:id,name'])->select(['id', 'employee_id', 'evaluator_id', 'scorecard_id', 'campaign_id', 'campaign_name', 'team_id', 'team_name', 'scorecard_name', 'call_at', 'status', 'percentage', 'result', 'has_critical_failure', 'updated_at', 'finalized_at']);
        $access->scope($query, $request->user());
        foreach (['employee' => 'employee_id', 'campaign' => 'campaign_id', 'team' => 'team_id', 'scorecard' => 'scorecard_id', 'evaluator' => 'evaluator_id', 'status' => 'status', 'result' => 'result'] as $filter => $column) {
            $query->when($filters[$filter] ?? null, fn ($q, $value) => $q->where($column, $value));
        }
        $query->when($filters['from'] ?? null, fn ($q, $value) => $q->whereDate('call_at', '>=', $value));
        $query->when($filters['to'] ?? null, fn ($q, $value) => $q->whereDate('call_at', '<=', $value));

        return Inertia::render('quality/evaluations/index', [
            'evaluations' => $query->latest('updated_at')->paginate(15)->withQueryString(),
            'filters' => $filters,
            'employees' => $this->employees()->when($request->user()->role === 'team_leader', fn ($employees) => $employees->filter(fn ($employee) => in_array($employee->teamMembership?->team_id, $access->teamIds($request->user()), true)))->values(),
            'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name']),
            'teams' => Team::query()->when($request->user()->role === 'team_leader', fn ($q) => $q->whereIn('id', $access->teamIds($request->user())))->orderBy('name')->get(['id', 'name', 'campaign_id']),
            'scorecards' => CallEvaluationScorecard::query()->orderBy('name')->get(['id', 'name', 'status']),
            'evaluators' => User::query()->whereIn('role', ['admin', 'manager'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('quality/evaluations/create', ['employees' => $this->employees(), 'scorecards' => CallEvaluationScorecard::query()->where('status', 'active')->with('campaigns:id,name')->orderBy('name')->get(['id', 'name', 'applies_to_all_campaigns'])]);
    }

    public function store(Request $request, CallEvaluationSnapshotService $snapshots): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('users', 'id')->whereIn('role', ['agent', 'team_leader'])],
            'scorecard_id' => ['required', 'integer', 'exists:call_evaluation_scorecards,id'],
            'call_at' => ['required', 'date'], 'call_direction' => ['required', Rule::in(['inbound', 'outbound'])],
            'call_reference' => ['nullable', 'string', 'max:255'], 'recording' => $this->recordingRules(false), 'evaluation_document' => $this->documentRules(),
        ]);
        $evaluation = $snapshots->createEvaluation(CallEvaluationScorecard::query()->findOrFail($data['scorecard_id']), User::query()->findOrFail($data['employee_id']), $request->user(), collect($data)->except(['employee_id', 'scorecard_id', 'recording', 'evaluation_document'])->all());
        if ($request->hasFile('recording')) {
            $this->saveRecording($request, $evaluation);
        }
        if ($request->hasFile('evaluation_document')) {
            $this->saveDocument($request, $evaluation);
        }
        $this->log($request->user()->id, 'Call Evaluation Created', $evaluation);

        return to_route('quality.evaluations.edit', $evaluation)->with('status', 'Evaluation draft created.');
    }

    public function edit(CallEvaluation $evaluation, CallEvaluationScoringService $scoring): Response
    {
        $evaluation->load(['employee:id,name,username', 'evaluator:id,name', 'criterionResults']);

        return Inertia::render('quality/evaluations/builder', ['evaluation' => $evaluation, 'review' => $scoring->calculate($evaluation, $evaluation->criterionResults->keyBy('criterion_snapshot_key'))]);
    }

    public function show(Request $request, CallEvaluation $evaluation, CallEvaluationScoringService $scoring, QaAccessService $access): Response
    {
        $access->authorizeTeam($request->user(), $evaluation->team_id);
        abort_unless($evaluation->status === 'submitted', 404);
        $evaluation->load(['employee:id,name,username', 'evaluator:id,name', 'criterionResults', 'coachingRecord:id,call_evaluation_id,status']);

        return Inertia::render('quality/evaluations/show', ['evaluation' => $evaluation, 'review' => $scoring->calculate($evaluation, $evaluation->criterionResults->keyBy('criterion_snapshot_key'))]);
    }

    public function update(Request $request, CallEvaluation $evaluation): RedirectResponse
    {
        $this->draft($evaluation);
        $data = $request->validate([
            'call_at' => ['sometimes', 'date'], 'call_direction' => ['sometimes', Rule::in(['inbound', 'outbound'])], 'call_reference' => ['nullable', 'string', 'max:255'],
            'strengths' => ['nullable', 'string', 'max:10000'], 'areas_for_improvement' => ['nullable', 'string', 'max:10000'], 'overall_feedback' => ['nullable', 'string', 'max:10000'], 'recommended_action' => ['nullable', 'string', 'max:10000'],
            'criteria' => ['sometimes', 'array'], 'criteria.*.key' => ['required', 'string', 'max:80'], 'criteria.*.points_awarded' => ['nullable', 'numeric', 'min:0'], 'criteria.*.is_na' => ['required', 'boolean'], 'criteria.*.comment' => ['nullable', 'string', 'max:5000'],
            'recording' => $this->recordingRules(false), 'evaluation_document' => $this->documentRules(),
        ]);
        DB::transaction(function () use ($data, $request, $evaluation): void {
            $criteria = $data['criteria'] ?? [];
            unset($data['criteria'], $data['recording'], $data['evaluation_document']);
            $evaluation->update($data);
            $definitions = collect($evaluation->scorecard_snapshot['categories'])->flatMap(fn ($category) => $category['criteria'])->keyBy('key');
            foreach ($criteria as $answer) {
                $definition = $definitions->get($answer['key']);
                if (! $definition) {
                    throw ValidationException::withMessages(['criteria' => 'A criterion does not belong to this Evaluation snapshot.']);
                }
                if ($answer['is_na'] && ! $definition['allows_na']) {
                    throw ValidationException::withMessages(['criteria' => "{$definition['label']} does not allow N/A."]);
                }
                if (! $answer['is_na'] && $answer['points_awarded'] !== null && (float) $answer['points_awarded'] > (float) $definition['points_possible']) {
                    throw ValidationException::withMessages(['criteria' => "{$definition['label']} exceeds its maximum points."]);
                }
                CallEvaluationCriterionResult::query()->updateOrCreate(['call_evaluation_id' => $evaluation->id, 'criterion_snapshot_key' => $answer['key']], ['criterion_id' => $definition['source_criterion_id'] ?? null, 'points_awarded' => $answer['is_na'] ? null : $answer['points_awarded'], 'is_na' => $answer['is_na'], 'critical_failure' => $definition['is_critical'] && ! $answer['is_na'] && (float) $answer['points_awarded'] === 0.0, 'comment' => $answer['comment'] ?? null]);
            }
            $this->log($request->user()->id, 'Call Evaluation Draft Saved', $evaluation);
        });
        if ($request->hasFile('recording')) {
            $this->saveRecording($request, $evaluation);
        }
        if ($request->hasFile('evaluation_document')) {
            $this->saveDocument($request, $evaluation);
        }

        return back()->with('status', 'Draft saved.');
    }

    public function submit(Request $request, CallEvaluation $evaluation, CallEvaluationScoringService $scoring): RedirectResponse
    {
        DB::transaction(function () use ($request, $evaluation, $scoring): void {
            $locked = CallEvaluation::query()->lockForUpdate()->findOrFail($evaluation->id);
            if ($locked->status === 'submitted') {
                return;
            }
            $this->draft($locked);
            $calculation = $scoring->calculate($locked, $locked->criterionResults()->get()->keyBy('criterion_snapshot_key'), true);
            $locked->update(['status' => 'submitted', 'points_earned' => $calculation['earned'], 'points_possible' => $calculation['possible'], 'percentage' => $calculation['percentage'], 'result' => $calculation['result'], 'has_critical_failure' => $calculation['critical'], 'finalized_at' => now(), 'finalized_by' => $request->user()->id]);
            app(EvaluationCoachingService::class)->sync($locked);
            $this->log($request->user()->id, 'Call Evaluation Submitted', $locked, ['result' => $calculation['result'], 'percentage' => $calculation['percentage']]);
        });

        return to_route('quality.evaluations.index')->with('status', 'Evaluation submitted.');
    }

    public function recording(Request $request, CallEvaluation $evaluation, QaAccessService $access): BinaryFileResponse
    {
        $access->authorizeTeam($request->user(), $evaluation->team_id);
        abort_unless($evaluation->status === 'submitted' || in_array($request->user()->role, ['admin', 'manager'], true), 404);
        abort_unless($evaluation->storage_disk && $evaluation->storage_key, 404);
        $disk = Storage::disk($evaluation->storage_disk);
        abort_unless($disk->exists($evaluation->storage_key), 404);

        return response()->file($disk->path($evaluation->storage_key), ['Content-Type' => $evaluation->mime_type, 'Content-Disposition' => 'inline; filename="recording"', 'Cache-Control' => 'private, no-store']);
    }

    public function document(Request $request, CallEvaluation $evaluation, QaAccessService $access): BinaryFileResponse
    {
        $access->authorizeTeam($request->user(), $evaluation->team_id);
        abort_unless($evaluation->status === 'submitted' || in_array($request->user()->role, ['admin', 'manager'], true), 404);
        abort_unless($evaluation->document_storage_disk && $evaluation->document_storage_key, 404);
        $disk = Storage::disk($evaluation->document_storage_disk);
        abort_unless($disk->exists($evaluation->document_storage_key), 404);

        return response()->download($disk->path($evaluation->document_storage_key), $evaluation->document_original_filename ?: 'call-evaluation.docx', ['Content-Type' => $evaluation->document_mime_type, 'Cache-Control' => 'private, no-store']);
    }

    public function destroy(Request $request, CallEvaluation $evaluation): RedirectResponse
    {
        $this->draft($evaluation);
        DB::transaction(function () use ($request, $evaluation): void {
            $this->log($request->user()->id, 'Call Evaluation Draft Deleted', $evaluation);
            $evaluation->delete();
        });
        if ($evaluation->storage_disk && $evaluation->storage_key) {
            Storage::disk($evaluation->storage_disk)->delete($evaluation->storage_key);
        }
        if ($evaluation->document_storage_disk && $evaluation->document_storage_key) {
            Storage::disk($evaluation->document_storage_disk)->delete($evaluation->document_storage_key);
        }

        return to_route('quality.evaluations.index')->with('status', 'Draft deleted.');
    }

    private function employees()
    {
        return User::query()->whereIn('role', ['agent', 'team_leader'])->where('status', 'active')->whereHas('teamMembership.team.campaign')->with('teamMembership.team.campaign:id,name')->orderBy('name')->get(['id', 'name', 'username']);
    }

    private function recordingRules(bool $required): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'max:'.(config('call-evaluations.max_recording_mb') * 1024), 'mimes:'.implode(',', config('call-evaluations.allowed_extensions')), 'mimetypes:'.implode(',', config('call-evaluations.allowed_mime_types'))];
    }

    private function documentRules(): array
    {
        return ['nullable', 'file', 'max:'.(config('call-evaluations.max_document_mb') * 1024), 'mimes:docx'];
    }

    private function saveRecording(Request $request, CallEvaluation $evaluation): void
    {
        $file = $request->file('recording');
        if (! $file) {
            return;
        }
        $disk = config('call-evaluations.recording_disk');
        $old = [$evaluation->storage_disk, $evaluation->storage_key];
        $key = $file->store("call-evaluations/{$evaluation->id}", $disk);
        throw_if(! $key, ValidationException::withMessages(['recording' => 'The recording could not be stored.']));
        $evaluation->update(['storage_disk' => $disk, 'storage_key' => $key, 'original_filename' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'uploaded_by' => $request->user()->id, 'uploaded_at' => now()]);
        if ($old[0] && $old[1] && ($old[0] !== $disk || $old[1] !== $key)) {
            Storage::disk($old[0])->delete($old[1]);
        }
        $this->log($request->user()->id, 'Call Recording Uploaded', $evaluation);
    }

    private function saveDocument(Request $request, CallEvaluation $evaluation): void
    {
        $file = $request->file('evaluation_document');
        if (! $file) {
            return;
        }
        $disk = config('call-evaluations.recording_disk');
        $old = [$evaluation->document_storage_disk, $evaluation->document_storage_key];
        $key = $file->store("call-evaluations/{$evaluation->id}/documents", $disk);
        throw_if(! $key, ValidationException::withMessages(['evaluation_document' => 'The evaluation document could not be stored.']));
        $evaluation->update(['document_storage_disk' => $disk, 'document_storage_key' => $key, 'document_original_filename' => $file->getClientOriginalName(), 'document_mime_type' => $file->getMimeType(), 'document_file_size' => $file->getSize(), 'document_uploaded_by' => $request->user()->id, 'document_uploaded_at' => now()]);
        if ($old[0] && $old[1] && ($old[0] !== $disk || $old[1] !== $key)) {
            Storage::disk($old[0])->delete($old[1]);
        }
        $this->log($request->user()->id, 'Call Evaluation Document Uploaded', $evaluation);
    }

    private function draft(CallEvaluation $evaluation): void
    {
        abort_unless($evaluation->status === 'draft', 409, 'Submitted Evaluations are read-only.');
    }

    private function log(int $actor, string $action, CallEvaluation $evaluation, array $metadata = []): void
    {
        DB::table('assessment_activity_logs')->insert(['actor_id' => $actor, 'action' => $action, 'target_type' => CallEvaluation::class, 'target_id' => $evaluation->id, 'metadata' => $metadata ? json_encode($metadata) : null, 'created_at' => now()]);
    }
}
