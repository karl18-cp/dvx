<?php

namespace App\Http\Controllers;

use App\Models\EmployeeClearance;
use App\Models\EmployeeContract;
use App\Models\User;
use App\Services\AssessmentNotificationService;
use App\Services\AuditLogService;
use App\Services\RolePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EmployeeClearanceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeManager($request->user());
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['all', 'not_started', 'in_progress', 'ready', 'approved'])],
        ]);

        $employees = User::query()->whereIn('status', ['resigned', 'terminated'])->where('role', '!=', 'trainee')
            ->with(['clearance.approver:id,name', 'attendanceRecords' => fn ($query) => $query->latest('attendance_date')->limit(1)])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($match) => $match->where('name', 'like', '%'.$search.'%')->orWhere('username', 'like', '%'.$search.'%')))
            ->orderBy('name')->get();

        $rows = $employees->map(fn (User $employee) => $this->row($employee))
            ->when(($filters['status'] ?? 'all') !== 'all', fn ($rows) => $rows->where('display_status', $filters['status']))
            ->values();

        return Inertia::render('employee-clearance', [
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $filters['status'] ?? 'all'],
            'rows' => $rows,
            'canApprove' => $request->user()->hasPermission('offboarding.approve'),
            'summary' => [
                'employees' => $employees->count(),
                'in_progress' => $employees->filter(fn ($employee) => in_array($employee->clearance?->status, ['in_progress', 'ready'], true))->count(),
                'ready' => $employees->where('clearance.status', 'ready')->count(),
                'approved' => $employees->where('clearance.status', 'approved')->count(),
            ],
        ]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $this->authorizeManager($request->user());
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'separation_date' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $employee = User::query()->whereKey($data['user_id'])->whereIn('status', ['resigned', 'terminated'])->where('role', '!=', 'trainee')->firstOrFail();

        $clearance = DB::transaction(function () use ($request, $audit, $data, $employee) {
            $clearance = EmployeeClearance::query()->firstOrCreate(
                ['user_id' => $employee->id],
                [
                    'separation_date' => $data['separation_date'], 'separation_type' => $employee->status,
                    'final_attendance_record_id' => $employee->attendanceRecords()->latest('attendance_date')->value('id'),
                    'quitclaim_contract_id' => $this->quitclaim($employee)?->id,
                    'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
                ],
            );
            $audit->record($request->user(), 'offboarding', 'Employee clearance opened', $clearance, metadata: ['employee_id' => $employee->id, 'separation_date' => $data['separation_date'], 'separation_type' => $employee->status], subjectId: $employee->id);

            return $clearance;
        });

        return back()->with('success', "Clearance case opened for {$employee->name}.");
    }

    public function updateStep(Request $request, EmployeeClearance $clearance, string $step, AuditLogService $audit)
    {
        $this->authorizeManager($request->user());
        abort_unless(in_array($step, ['property', 'attendance', 'outstanding', 'quitclaim', 'interview'], true), 404);
        if ($clearance->status === 'approved') {
            throw ValidationException::withMessages(['clearance' => 'Approved clearance records are locked.']);
        }

        $attributes = match ($step) {
            'property' => $this->propertyData($request),
            'attendance' => $this->attendanceData($request, $clearance),
            'outstanding' => $this->outstandingData($request),
            'quitclaim' => $this->quitclaimData($request, $clearance),
            'interview' => $this->interviewData($request),
        };

        DB::transaction(function () use ($request, $clearance, $step, $attributes, $audit) {
            $clearance = EmployeeClearance::query()->lockForUpdate()->findOrFail($clearance->id);
            $before = $clearance->only(array_keys($attributes));
            $clearance->update([...$attributes, 'updated_by' => $request->user()->id]);
            $this->refreshStatus($clearance);
            $audit->record($request->user(), 'offboarding', 'Employee clearance step updated', $clearance, metadata: ['step' => $step, 'before' => $before, 'after' => $attributes], subjectId: $clearance->user_id);
        });

        return back()->with('success', ucfirst($step).' clearance updated.');
    }

    public function approve(Request $request, EmployeeClearance $clearance, AuditLogService $audit, AssessmentNotificationService $notifications)
    {
        abort_unless($request->user()->hasPermission('offboarding.approve'), 403);
        $data = $request->validate(['approval_notes' => ['required', 'string', 'min:5', 'max:2000']]);

        DB::transaction(function () use ($request, $clearance, $data, $audit, $notifications) {
            $clearance = EmployeeClearance::query()->lockForUpdate()->findOrFail($clearance->id);
            $readiness = $this->readiness($clearance);
            if (! $readiness['ready']) {
                throw ValidationException::withMessages(['clearance' => 'Complete every required clearance step before final approval.']);
            }
            $clearance->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'approval_notes' => $data['approval_notes'], 'updated_by' => $request->user()->id]);
            $audit->record($request->user(), 'offboarding', 'Final employee clearance approved', $clearance, metadata: ['approval_notes' => $data['approval_notes'], 'checks' => $readiness['checks']], subjectId: $clearance->user_id);
            $notifications->createForUser($clearance->employee, "clearance:{$clearance->id}:approved", 'offboarding', 'Final clearance approved', 'Your employee clearance has received final approval.', null);
        });

        return back()->with('success', 'Final employee clearance approved and locked.');
    }

    private function row(User $employee): array
    {
        $clearance = $employee->clearance;
        $backpay = $clearance?->backpay();
        $quitclaim = $this->quitclaim($employee);
        $lastAttendance = $employee->attendanceRecords->first();
        $readiness = $clearance ? $this->readiness($clearance, $backpay, $quitclaim) : null;

        return [
            'employee' => ['id' => $employee->id, 'name' => $employee->name, 'username' => $employee->username, 'role' => $employee->role, 'status' => $employee->status, 'avatar' => $employee->avatar],
            'display_status' => $clearance?->status ?? 'not_started',
            'clearance' => $clearance ? [
                ...$clearance->only(['id', 'separation_date', 'separation_type', 'status', 'property_status', 'property_items', 'property_notes', 'final_attendance_status', 'final_attendance_notes', 'outstanding_status', 'outstanding_amount_cents', 'outstanding_notes', 'quitclaim_status', 'quitclaim_notes', 'exit_interview_status', 'exit_interview_at', 'exit_interview_notes', 'approval_notes']),
                'approved_at' => $clearance->approved_at?->toIso8601String(), 'approver' => $clearance->approver?->name,
            ] : null,
            'backpay' => $backpay ? $backpay->only(['id', 'status', 'total_cents', 'separation_date']) : null,
            'quitclaim' => $quitclaim ? $quitclaim->only(['id', 'title', 'status', 'original_filename', 'effective_date']) : null,
            'last_attendance' => $lastAttendance ? ['id' => $lastAttendance->id, 'date' => $lastAttendance->attendance_date?->toDateString(), 'status' => $lastAttendance->status, 'total_minutes' => $lastAttendance->total_minutes] : null,
            'checks' => $readiness['checks'] ?? [], 'completed' => $readiness['completed'] ?? 0, 'total' => 7, 'ready' => $readiness['ready'] ?? false,
        ];
    }

    private function readiness(EmployeeClearance $clearance, $backpay = null, ?EmployeeContract $quitclaim = null): array
    {
        $backpay ??= $clearance->backpay();
        $quitclaim ??= $this->quitclaim($clearance->employee);
        $checks = [
            'property' => in_array($clearance->property_status, ['returned', 'waived'], true),
            'attendance' => $clearance->final_attendance_status === 'verified',
            'outstanding' => in_array($clearance->outstanding_status, ['cleared', 'waived'], true),
            'backpay' => $backpay && in_array($backpay->status, ['ready', 'claimed'], true),
            'quitclaim' => $clearance->quitclaim_status === 'waived' || ($clearance->quitclaim_status === 'signed' && $quitclaim),
            'interview' => in_array($clearance->exit_interview_status, ['completed', 'declined', 'waived'], true),
            'account' => in_array($clearance->employee->status, ['resigned', 'terminated'], true) && ! $clearance->employee->canAccessAccount(),
        ];

        return ['checks' => $checks, 'completed' => collect($checks)->filter()->count(), 'ready' => ! in_array(false, $checks, true)];
    }

    private function refreshStatus(EmployeeClearance $clearance): void
    {
        $clearance->loadMissing('employee');
        $clearance->update(['status' => $this->readiness($clearance)['ready'] ? 'ready' : 'in_progress']);
    }

    private function propertyData(Request $request): array
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'partial', 'returned', 'waived'])],
            'items' => ['nullable', 'array', 'max:25'], 'items.*.name' => ['required', 'string', 'max:120'],
            'items.*.status' => ['required', Rule::in(['outstanding', 'returned', 'damaged', 'waived'])],
            'items.*.notes' => ['nullable', 'string', 'max:500'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return ['property_status' => $data['status'], 'property_items' => $data['items'] ?? [], 'property_notes' => $data['notes'] ?? null];
    }

    private function attendanceData(Request $request, EmployeeClearance $clearance): array
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'verified'])], 'notes' => ['nullable', 'string', 'max:2000']]);
        $recordId = $clearance->employee->attendanceRecords()->latest('attendance_date')->value('id');
        if ($data['status'] === 'verified' && ! $recordId && empty(trim($data['notes'] ?? ''))) {
            throw ValidationException::withMessages(['notes' => 'Explain why final attendance is verified without an attendance record.']);
        }

        return ['final_attendance_status' => $data['status'], 'final_attendance_record_id' => $recordId, 'final_attendance_notes' => $data['notes'] ?? null];
    }

    private function outstandingData(Request $request): array
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'cleared', 'waived'])], 'amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'], 'notes' => ['nullable', 'string', 'max:2000']]);

        return ['outstanding_status' => $data['status'], 'outstanding_amount_cents' => (int) round(((float) $data['amount']) * 100), 'outstanding_notes' => $data['notes'] ?? null];
    }

    private function quitclaimData(Request $request, EmployeeClearance $clearance): array
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'signed', 'waived'])], 'notes' => ['nullable', 'string', 'max:2000']]);
        $contract = $this->quitclaim($clearance->employee);
        if ($data['status'] === 'signed' && ! $contract) {
            throw ValidationException::withMessages(['status' => 'Upload and link a quitclaim document in Employee Contracts before marking it signed.']);
        }

        return ['quitclaim_status' => $data['status'], 'quitclaim_contract_id' => $contract?->id, 'quitclaim_notes' => $data['notes'] ?? null];
    }

    private function interviewData(Request $request): array
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'completed', 'declined', 'waived'])], 'interview_at' => ['nullable', 'required_if:status,completed', 'date'], 'notes' => ['nullable', 'string', 'max:3000']]);

        return ['exit_interview_status' => $data['status'], 'exit_interview_at' => $data['interview_at'] ?? null, 'exit_interview_notes' => $data['notes'] ?? null];
    }

    private function quitclaim(User $employee): ?EmployeeContract
    {
        return EmployeeContract::query()->where('user_id', $employee->id)->where('contract_type', 'quitclaim')->whereNull('archived_at')->latest('id')->first();
    }

    private function authorizeManager(User $actor): void
    {
        abort_unless(app(RolePermissionService::class)->can($actor, 'offboarding.manage'), 403);
    }
}
