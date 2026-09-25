<?php

namespace App\Http\Controllers;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentTrainingAttachment;
use App\Models\AssessmentTrainingAttachmentProgress;
use App\Models\AssessmentTrainingMaterial;
use App\Models\AssessmentTrainingProgress;
use App\Services\AssessmentAttemptService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeAssessmentController extends Controller
{
    private const ASSIGNMENT_TIMEZONE = 'Asia/Manila';

    public function index(Request $request, AdminLearningOverviewController $overview): Response
    {
        if ($request->user()->role === 'admin') {
            return $overview->assessments($request);
        }

        $assignments = AssessmentAssignment::query()->where('employee_id', $request->user()->id)
            ->with(['assessment.category:id,name', 'assessment.trainingMaterials' => fn ($q) => $q->select(['id', 'assessment_id', 'is_required'])->where('is_active', true)->with(['trainingProgress' => fn ($p) => $p->select(['id', 'material_id', 'completed_at'])->where('employee_id', $request->user()->id)]), 'assessment.trainingAttachments' => fn ($q) => $q->with(['material.campaigns:id', 'progress' => fn ($p) => $p->where('employee_id', $request->user()->id)]), 'attempts' => fn ($q) => $q->select(['id', 'assignment_id', 'employee_id', 'attempt_number', 'question_snapshot', 'submitted_at', 'status'])->where('employee_id', $request->user()->id)->latest('attempt_number')])
            ->get()->map(function ($assignment): array {
                $attempt = $assignment->attempts->first();
                $assessment = $assignment->assessment;
                $now = now();
                $requiredMaterials = $assessment->trainingMaterials->where('is_required', true);
                $libraryMaterials = $assessment->trainingAttachments->filter(fn ($attachment) => $attachment->material?->applies_to_all_campaigns
                    || ($assignment->campaign_id && $attachment->material?->campaigns->contains('id', $assignment->campaign_id)));
                $requiredLibraryMaterials = $libraryMaterials->where('is_required', true);
                $requiredCompleted = $requiredMaterials->filter(fn ($material) => $material->trainingProgress->first()?->completed_at !== null)->count()
                    + $requiredLibraryMaterials->filter(fn ($attachment) => $attachment->progress->first()?->completed_at !== null)->count();
                $requiredTotal = $requiredMaterials->count() + $requiredLibraryMaterials->count();
                $trainingComplete = $requiredCompleted === $requiredTotal;
                $status = $attempt?->status;
                if (! $status) {
                    $available = $assignment->effectiveAvailableAt();
                    $status = $available && $now->lt($available) ? 'not_available' : (! $trainingComplete ? 'training_required' : 'ready');
                }
                $due = $assignment->effectiveDueAt();
                $completedOnTime = $attempt?->submitted_at
                    && in_array($attempt->status, ['passed', 'failed', 'pending_review'], true)
                    && (! $due || $attempt->submitted_at->lte($due));

                if (($due && $now->gt($due)) && ! $completedOnTime && ! in_array($status, ['passed', 'pending_review'], true)) {
                    $status = 'overdue';
                }
                $canRetake = $attempt && in_array($attempt->status, ['failed', 'passed'], true) && $assessment->allow_retake && $assignment->attempts->count() < $assessment->maximum_attempts;

                $hasQuestionSource = $assessment->questions()->whereNull('generated_for_attempt_id')->exists()
                    || $assessment->randomPools()->exists();
                $emptyActiveAttempt = $attempt?->status === 'in_progress' && empty($attempt->question_snapshot) && ! $hasQuestionSource;

                return ['id' => $assignment->id, 'title' => $assessment->title, 'category' => $assessment->category?->name, 'available_from' => $assignment->effectiveAvailableAt()?->toIso8601String(), 'due_date' => $due?->toIso8601String(), 'due_label' => $completedOnTime ? null : $this->dueLabel($due), 'passing_score' => (float) $assessment->passing_score, 'time_limit_minutes' => $assessment->time_limit_minutes, 'maximum_attempts' => $assessment->maximum_attempts, 'attempts_used' => $assignment->attempts->count(), 'status' => $emptyActiveAttempt ? 'configuration_error' : $status, 'configuration_error' => $emptyActiveAttempt ? 'This assessment has no questions. Please contact your administrator.' : null, 'training_complete' => $trainingComplete, 'required_training_total' => $requiredTotal, 'required_training_completed' => $requiredCompleted, 'has_training' => $assessment->trainingMaterials->isNotEmpty() || $libraryMaterials->isNotEmpty(), 'active_attempt_id' => $emptyActiveAttempt ? null : ($attempt?->status === 'in_progress' ? $attempt->id : null), 'latest_attempt_id' => $attempt && $attempt->status !== 'in_progress' ? $attempt->id : null, 'can_retake' => $canRetake, 'assigned_at' => $assignment->assigned_at?->toIso8601String()];
            })->sortBy(fn (array $assignment): array => $this->assignmentSortKey($assignment))->values();

        $weekStart = now(self::ASSIGNMENT_TIMEZONE)->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        $thisWeek = $assignments->filter(function (array $assignment) use ($weekStart, $weekEnd): bool {
            $date = Carbon::parse($assignment['available_from'] ?? $assignment['assigned_at'])->setTimezone(self::ASSIGNMENT_TIMEZONE);

            return $date->betweenIncluded($weekStart, $weekEnd);
        });
        $completedThisWeek = $thisWeek->whereIn('status', ['passed', 'failed', 'pending_review'])->count();

        return Inertia::render('assessments/index', ['assignments' => $assignments, 'week_summary' => ['total' => $thisWeek->count(), 'completed' => $completedThisWeek, 'remaining' => $thisWeek->count() - $completedThisWeek]]);
    }

    public function training(Request $request, AssessmentAssignment $assignment): Response
    {
        $this->ownAssignment($request, $assignment);
        $materials = $assignment->assessment->trainingMaterials()->where('is_active', true)->with(['trainingProgress' => fn ($q) => $q->where('employee_id', $request->user()->id)])->orderBy('display_order')->get()->map(fn ($material) => ['id' => $material->id, 'title' => $material->title, 'description' => $material->description, 'type' => $material->type, 'content' => $material->type === 'written' ? $material->content : null, 'required' => $material->is_required, 'threshold' => $material->required_completion_percentage, 'duration_seconds' => $material->duration_seconds, 'opened' => (bool) $material->trainingProgress->first()?->started_at, 'completed' => (bool) $material->trainingProgress->first()?->completed_at, 'media_url' => $material->type === 'written' ? null : route('assessments.my.materials.media', [$assignment, $material])]);
        $library = $assignment->assessment->trainingAttachments()
            ->whereHas('material', fn ($q) => $q->where(fn ($scope) => $scope->where('applies_to_all_campaigns', true)->when($assignment->campaign_id, fn ($x, $campaign) => $x->orWhereHas('campaigns', fn ($c) => $c->whereKey($campaign)))))
            ->with(['material', 'progress' => fn ($q) => $q->where('employee_id', $request->user()->id)])->get()->map(fn ($a) => ['id' => 'library-'.$a->id, 'title' => $a->material->title, 'description' => $a->material->description, 'type' => $a->material->type, 'content' => $a->material->type === 'written' ? $a->material->content : null, 'required' => $a->is_required, 'threshold' => $a->required_completion_percentage, 'duration_seconds' => $a->material->duration_seconds, 'opened' => (bool) $a->progress->first()?->started_at, 'completed' => (bool) $a->progress->first()?->completed_at, 'media_url' => $a->material->type === 'written' ? null : route('assessments.my.library.media', [$assignment, $a])]);
        $materials = $materials->concat($library)->values();

        return Inertia::render('assessments/training', ['assignment' => ['id' => $assignment->id, 'title' => $assignment->assessment->title], 'materials' => $materials]);
    }

    public function openLibraryMaterial(Request $request, AssessmentAssignment $assignment, AssessmentTrainingAttachment $attachment): JsonResponse
    {
        $this->ownLibraryAttachment($request, $assignment, $attachment);
        AssessmentTrainingAttachmentProgress::query()->firstOrCreate(['attachment_id' => $attachment->id, 'employee_id' => $request->user()->id], ['started_at' => now()]);

        return response()->json(['opened' => true]);
    }

    public function completeLibraryMaterial(Request $request, AssessmentAssignment $assignment, AssessmentTrainingAttachment $attachment): JsonResponse
    {
        $this->ownLibraryAttachment($request, $assignment, $attachment);
        $progress = AssessmentTrainingAttachmentProgress::query()->where(['attachment_id' => $attachment->id, 'employee_id' => $request->user()->id])->first();
        abort_unless($progress?->started_at, 422, 'Open the material first.');
        $progress->update(['completion_percentage' => 100, 'completed_at' => $progress->completed_at ?? now()]);

        return response()->json(['completed' => true]);
    }

    public function libraryMedia(Request $request, AssessmentAssignment $assignment, AssessmentTrainingAttachment $attachment): StreamedResponse
    {
        $this->ownLibraryAttachment($request, $assignment, $attachment);
        $material = $attachment->material;
        abort_unless($material->storage_disk && $material->storage_key, 404);
        $disk = Storage::disk($material->storage_disk);
        abort_unless($disk->exists($material->storage_key), 404);

        return response()->streamDownload(fn () => fpassthru($disk->readStream($material->storage_key)), $material->original_filename, ['Content-Type' => $material->mime_type, 'Content-Disposition' => 'inline']);
    }

    private function ownLibraryAttachment(Request $request, AssessmentAssignment $assignment, AssessmentTrainingAttachment $attachment): void
    {
        $this->ownAssignment($request, $assignment);
        abort_unless($attachment->assessment_id === $assignment->assessment_id, 404);
        $attachment->loadMissing('material');
        abort_unless($attachment->material->applies_to_all_campaigns || ($assignment->campaign_id && $attachment->material->campaigns()->whereKey($assignment->campaign_id)->exists()), 404);
    }

    public function openMaterial(Request $request, AssessmentAssignment $assignment, AssessmentTrainingMaterial $material): JsonResponse
    {
        $this->ownMaterial($request, $assignment, $material);
        AssessmentTrainingProgress::query()->firstOrCreate(['material_id' => $material->id, 'employee_id' => $request->user()->id], ['started_at' => now()]);

        return response()->json(['opened' => true]);
    }

    public function completeMaterial(Request $request, AssessmentAssignment $assignment, AssessmentTrainingMaterial $material): JsonResponse
    {
        $this->ownMaterial($request, $assignment, $material);
        $progress = AssessmentTrainingProgress::query()->where(['material_id' => $material->id, 'employee_id' => $request->user()->id])->first();
        abort_unless($progress?->started_at, 422, 'Open the material before marking it complete.');
        $data = $request->validate(['position_seconds' => ['nullable', 'integer', 'min:0'], 'completion_percentage' => ['nullable', 'numeric', 'between:0,100']]);
        $percentage = in_array($material->type, ['video', 'audio'], true) ? (float) ($data['completion_percentage'] ?? 0) : 100;
        abort_if($percentage < $material->required_completion_percentage, 422, 'The completion threshold has not been reached.');
        $progress->update(['last_position_seconds' => $data['position_seconds'] ?? $progress->last_position_seconds, 'maximum_position_seconds' => max($progress->maximum_position_seconds, $data['position_seconds'] ?? 0), 'completion_percentage' => $percentage, 'completed_at' => $progress->completed_at ?? now()]);
        DB::table('assessment_activity_logs')->insert(['actor_id' => $request->user()->id, 'action' => 'Training Completed', 'target_type' => AssessmentTrainingMaterial::class, 'target_id' => $material->id, 'metadata' => json_encode(['assignment_id' => $assignment->id]), 'created_at' => now()]);

        return response()->json(['completed' => true]);
    }

    public function media(Request $request, AssessmentAssignment $assignment, AssessmentTrainingMaterial $material): StreamedResponse
    {
        $this->ownMaterial($request, $assignment, $material);
        abort_unless($material->storage_disk && $material->storage_key, 404);
        $disk = Storage::disk($material->storage_disk);
        abort_unless($disk->exists($material->storage_key), 404);

        return response()->streamDownload(fn () => fpassthru($disk->readStream($material->storage_key)), $material->original_filename, ['Content-Type' => $material->mime_type, 'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $material->original_filename).'"']);
    }

    public function start(Request $request, AssessmentAssignment $assignment, AssessmentAttemptService $service): RedirectResponse
    {
        $this->ownAssignment($request, $assignment);
        $attempt = $service->start($assignment, $request->user());

        return redirect()->route('assessments.my.attempts.show', $attempt);
    }

    public function showAttempt(Request $request, AssessmentAttempt $attempt, AssessmentAttemptService $service): Response|RedirectResponse
    {
        $this->ownAttempt($request, $attempt);
        if (empty($attempt->question_snapshot)) {
            $replacement = $service->start($attempt->assignment, $request->user());
            if ($replacement->isNot($attempt)) {
                return redirect()->route('assessments.my.attempts.show', $replacement);
            }

            return redirect()->route('assessments.index')->withErrors(['assessment' => 'This assessment has no questions. Please contact your administrator.']);
        }
        if ($attempt->status !== 'in_progress') {
            return redirect()->route('assessments.my.results', $attempt);
        }
        if ($attempt->expires_at && now()->gte($attempt->expires_at)) {
            $service->submit($attempt);

            return redirect()->route('assessments.my.results', $attempt);
        }
        $answers = $attempt->answers()->get()->mapWithKeys(fn ($answer) => [(string) $answer->question_id => $answer->answer]);
        $questions = collect($attempt->question_snapshot)->map(fn ($q) => collect($q)->except(['feedback'])->put('options', collect($q['options'])->map(fn ($o) => collect($o)->except('correct')->all())->all())->all());

        return Inertia::render('assessments/take', ['attempt' => ['id' => $attempt->id, 'title' => $attempt->assessment->title, 'attempt_number' => $attempt->attempt_number, 'expires_at' => $attempt->expires_at?->toIso8601String(), 'server_time' => now()->toIso8601String(), 'questions' => $questions, 'answers' => $answers, 'flagged_question_ids' => $attempt->flagged_question_ids ?? []]]);
    }

    public function review(Request $request, AssessmentAttempt $attempt, AssessmentAttemptService $service): Response|RedirectResponse
    {
        $this->ownAttempt($request, $attempt);
        if ($attempt->status !== 'in_progress') {
            return redirect()->route('assessments.my.results', $attempt);
        }
        if ($attempt->expires_at && now()->gte($attempt->expires_at)) {
            $service->submit($attempt);

            return redirect()->route('assessments.my.results', $attempt);
        }

        $answers = $attempt->answers()->get()->keyBy('question_id');
        $questions = collect($attempt->question_snapshot)->values()->map(function (array $question, int $index) use ($answers): array {
            $answer = $answers->get($question['id'])?->answer ?? [];
            $answered = filled(trim((string) ($answer['text'] ?? ''))) || count($answer['option_ids'] ?? []) > 0;

            return [
                'id' => $question['id'],
                'number' => $index + 1,
                'preview' => str($question['text'])->squish()->limit(100)->toString(),
                'answered' => $answered,
            ];
        });

        return Inertia::render('assessments/review', ['attempt' => [
            'id' => $attempt->id,
            'title' => $attempt->assessment->title,
            'attempt_number' => $attempt->attempt_number,
            'expires_at' => $attempt->expires_at?->toIso8601String(),
            'server_time' => now()->toIso8601String(),
            'questions' => $questions,
            'answered_count' => $questions->where('answered', true)->count(),
            'unanswered_count' => $questions->where('answered', false)->count(),
        ]]);
    }

    public function autosave(Request $request, AssessmentAttempt $attempt, AssessmentAttemptService $service): JsonResponse
    {
        $this->ownAttempt($request, $attempt);
        $data = $request->validate(['question_id' => ['required', 'integer'], 'answer' => ['required', 'array']]);
        $answer = $service->saveAnswer($attempt, $data['question_id'], $data['answer']);

        return response()->json(['saved' => true, 'saved_at' => $answer->updated_at->toIso8601String()]);
    }

    public function flag(Request $request, AssessmentAttempt $attempt, AssessmentAttemptService $service): JsonResponse
    {
        $this->ownAttempt($request, $attempt);
        $service->assertWritable($attempt);
        $data = $request->validate(['question_id' => ['required', 'integer'], 'flagged' => ['required', 'boolean']]);
        abort_unless(collect($attempt->question_snapshot)->contains('id', $data['question_id']), 422, 'Question does not belong to this attempt.');
        $flags = collect($attempt->flagged_question_ids ?? [])->map(fn ($id) => (int) $id);
        $flags = $data['flagged'] ? $flags->push($data['question_id'])->unique() : $flags->reject(fn ($id) => $id === $data['question_id']);
        $attempt->update(['flagged_question_ids' => $flags->values()->all()]);

        return response()->json(['saved' => true, 'flagged_question_ids' => $attempt->flagged_question_ids]);
    }

    public function submit(Request $request, AssessmentAttempt $attempt, AssessmentAttemptService $service): RedirectResponse
    {
        $this->ownAttempt($request, $attempt);
        $service->submit($attempt);

        return redirect()->route('assessments.my.results', $attempt);
    }

    public function result(Request $request, AssessmentAttempt $attempt): Response
    {
        $this->ownAttempt($request, $attempt);
        abort_if($attempt->status === 'in_progress', 422);
        $attempt->load(['assessment', 'answers', 'skillResults.skill']);
        $showKey = $attempt->assessment->show_correct_answers;
        $answers = $attempt->answers->keyBy('question_id');
        $correct = $attempt->answers->where('is_correct', true)->count();
        $incorrect = $attempt->answers->where('is_correct', false)->count();
        $questions = collect($attempt->question_snapshot)->map(function ($q) use ($answers, $showKey) {
            $answer = $answers->get($q['id']);
            $row = ['id' => $q['id'], 'text' => $q['text'], 'type' => $q['type'], 'answer' => $answer?->answer, 'grader_feedback' => $answer?->grader_feedback];
            if ($showKey) {
                $row += ['is_correct' => $answer?->is_correct, 'correct_option_ids' => collect($q['options'])->where('correct', true)->pluck('id'), 'options' => collect($q['options'])->map(fn ($o) => ['id' => $o['id'], 'text' => $o['text']]), 'feedback' => $q['feedback']];
            }

            return $row;
        });

        $isPendingReview = $attempt->status === 'pending_review';

        return Inertia::render('assessments/result', ['result' => ['id' => $attempt->id, 'title' => $attempt->assessment->title, 'attempt_number' => $attempt->attempt_number, 'score' => $isPendingReview ? null : (float) $attempt->points_earned, 'total' => (float) $attempt->total_points, 'percentage' => $isPendingReview ? null : (float) $attempt->percentage, 'passing_score' => (float) $attempt->assessment->passing_score, 'passed' => $isPendingReview ? null : $attempt->passed, 'status' => $attempt->status, 'correct_count' => $correct, 'incorrect_count' => $incorrect, 'unanswered_count' => count($attempt->question_snapshot) - $attempt->answers->count(), 'time_taken_seconds' => $attempt->time_taken_seconds, 'submitted_at' => $attempt->submitted_at?->toIso8601String(), 'show_correct_answers' => $showKey, 'questions' => $questions, 'skills' => $attempt->skillResults->map(fn ($s) => ['name' => $s->skill->name, 'earned' => (float) $s->points_earned, 'possible' => (float) $s->points_possible, 'percentage' => (float) $s->percentage])]]);
    }

    private function ownAssignment(Request $request, AssessmentAssignment $assignment): void
    {
        abort_unless($assignment->employee_id === $request->user()->id, 403);
        $assignment->loadMissing('assessment');
    }

    private function ownAttempt(Request $request, AssessmentAttempt $attempt): void
    {
        abort_unless($attempt->employee_id === $request->user()->id || in_array($request->user()->role, ['admin', 'manager'], true), 403);
        $attempt->loadMissing('assessment');
    }

    private function ownMaterial(Request $request, AssessmentAssignment $assignment, AssessmentTrainingMaterial $material): void
    {
        $this->ownAssignment($request, $assignment);
        abort_unless($material->assessment_id === $assignment->assessment_id && $material->is_active, 404);
    }

    private function dueLabel(?CarbonInterface $due): ?string
    {
        if (! $due) {
            return null;
        }
        $today = now(self::ASSIGNMENT_TIMEZONE)->startOfDay();
        $dueDate = $due->copy()->setTimezone(self::ASSIGNMENT_TIMEZONE)->startOfDay();
        $days = (int) $today->diffInDays($dueDate, false);

        return match (true) {
            $days < -1 => 'Overdue by '.abs($days).' Days',
            $days === -1 => 'Overdue by 1 Day',
            $days === 0 => 'Due Today',
            $days === 1 => 'Due Tomorrow',
            $days <= 3 => "Due in {$days} Days",
            default => null,
        };
    }

    /** @return array{int, string, string} */
    private function assignmentSortKey(array $assignment): array
    {
        $completed = in_array($assignment['status'], ['passed', 'failed', 'pending_review'], true);
        $due = $assignment['due_date'] ? Carbon::parse($assignment['due_date']) : null;
        $available = $assignment['available_from'] ? Carbon::parse($assignment['available_from']) : null;
        $now = now();
        $today = now(self::ASSIGNMENT_TIMEZONE)->startOfDay();
        $dueDay = $due?->copy()->setTimezone(self::ASSIGNMENT_TIMEZONE)->startOfDay();

        $priority = match (true) {
            $completed => 5,
            $due !== null && $due->lt($now) => 0,
            $dueDay !== null && $dueDay->equalTo($today) => 1,
            $dueDay !== null && $dueDay->betweenIncluded($today->copy()->addDay(), $today->copy()->addDays(3)) => 2,
            $available === null || $available->lte($now) => 3,
            default => 4,
        };

        return [$priority, $due?->toIso8601String() ?? '9999-12-31', $available?->toIso8601String() ?? $assignment['assigned_at']];
    }
}
