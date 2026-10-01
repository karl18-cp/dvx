<?php

namespace App\Http\Controllers;

use App\Models\EmployeeBackpay;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\ThirteenthMonthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeBackpayController extends Controller
{
    private const STATUSES = ['pending_clearance', 'ready', 'claimed'];

    public function index(Request $request, AccountingService $accounting, ThirteenthMonthService $service): JsonResponse
    {
        $accounting->authorize($request->user());
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2020', 'max:'.now('Asia/Manila')->year],
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);
        $year = (int) ($filters['year'] ?? now('Asia/Manila')->year);
        $employees = User::query()->whereNotNull('username')->where('role', '!=', 'trainee')
            ->whereIn('status', ['resigned', 'terminated'])
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($match) => $match->where('name', 'like', '%'.$value.'%')->orWhere('username', 'like', '%'.$value.'%')))
            ->orderBy('name')->get(['id', 'name', 'username', 'role', 'status']);
        $accruals = $service->rows($year, $employees)->keyBy(fn ($row) => $row['employee']['id']);
        $records = EmployeeBackpay::with(['employee:id,name,username,role,status', 'creator:id,name', 'updater:id,name'])
            ->where('calendar_year', $year)
            ->whereIn('user_id', $employees->pluck('id'))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->latest('separation_date')->get()->keyBy('user_id');
        $rows = $employees->map(fn ($employee) => [
            'employee' => $employee,
            'accrual' => $accruals->get($employee->id),
            'backpay' => $records->get($employee->id),
        ])->filter(fn ($row) => ! isset($filters['status']) || $row['backpay'])->values();

        return response()->json([
            'rows' => $rows,
            'summary' => [
                'eligible_employees' => $employees->count(),
                'pending' => $records->where('status', 'pending_clearance')->count(),
                'ready' => $records->where('status', 'ready')->count(),
                'claimed' => $records->where('status', 'claimed')->count(),
                'total_cents' => $records->sum('total_cents'),
            ],
            'year' => $year,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, AccountingService $accounting, ThirteenthMonthService $service): RedirectResponse
    {
        $accounting->authorize($request->user());
        $data = $request->validate($this->rules(true));
        $employee = User::whereKey($data['user_id'])->whereIn('status', ['resigned', 'terminated'])->firstOrFail();

        DB::transaction(function () use ($request, $accounting, $service, $employee, $data) {
            if (EmployeeBackpay::where('request_key', $data['request_key'])->exists()) {
                return;
            }
            $this->save($request, $accounting, $service, $employee, new EmployeeBackpay, $data);
        });

        return back()->with('status', 'Backpay record prepared from the employee’s paid payroll history.');
    }

    public function update(Request $request, EmployeeBackpay $backpay, AccountingService $accounting, ThirteenthMonthService $service): RedirectResponse
    {
        $accounting->authorize($request->user());
        $data = $request->validate($this->rules(false));
        DB::transaction(function () use ($request, $accounting, $service, $backpay, $data) {
            $backpay = EmployeeBackpay::whereKey($backpay->id)->lockForUpdate()->firstOrFail();
            if ($backpay->status === 'claimed') {
                throw ValidationException::withMessages(['backpay' => 'Claimed backpay records are read-only.']);
            }
            $employee = User::whereKey($backpay->user_id)->whereIn('status', ['resigned', 'terminated'])->firstOrFail();
            $this->save($request, $accounting, $service, $employee, $backpay, $data);
        });

        return back()->with('status', 'Backpay record updated.');
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(bool $creating): array
    {
        return [
            'request_key' => [$creating ? 'required' : 'nullable', 'uuid'],
            'user_id' => [$creating ? 'required' : 'nullable', 'integer', 'exists:users,id'],
            'separation_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'last_payroll' => ['required', 'string', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'additions' => ['required', 'string', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'deductions' => ['required', 'string', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'claimed_at' => ['nullable', 'required_if:status,claimed', 'date_format:Y-m-d', 'after_or_equal:separation_date', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    private function save(Request $request, AccountingService $accounting, ThirteenthMonthService $service, User $employee, EmployeeBackpay $backpay, array $data): void
    {
        $year = (int) substr($data['separation_date'], 0, 4);
        $accrual = $service->employee($employee, $year);
        $last = $accounting->money($data['last_payroll']);
        $additions = $accounting->money($data['additions']);
        $deductions = $accounting->money($data['deductions']);
        $total = $accrual['thirteenth_month_cents'] + $last + $additions - $deductions;
        if ($total < 0) {
            throw ValidationException::withMessages(['deductions' => 'Deductions cannot exceed the backpay earnings.']);
        }
        $creating = ! $backpay->exists;
        $backpay->fill([
            ...($creating ? ['request_key' => $data['request_key'], 'user_id' => $employee->id, 'created_by' => $request->user()->id] : []),
            'separation_date' => $data['separation_date'],
            'separation_status' => $employee->status,
            'calendar_year' => $year,
            'eligible_basic_cents' => $accrual['eligible_basic_cents'],
            'thirteenth_month_cents' => $accrual['thirteenth_month_cents'],
            'last_payroll_cents' => $last,
            'additions_cents' => $additions,
            'deductions_cents' => $deductions,
            'total_cents' => $total,
            'status' => $data['status'],
            'claimed_at' => $data['status'] === 'claimed' ? $data['claimed_at'] : null,
            'notes' => $data['notes'] ?? null,
            'source_snapshot' => [
                'formula' => 'paid basic payroll earnings divided by 12',
                'payroll_entry_ids' => $accrual['payroll_entry_ids'],
                'payroll_count' => $accrual['payroll_count'],
                'eligible_minutes' => $accrual['eligible_minutes'],
                'missing_payroll_count' => $accrual['missing_payroll_count'],
                'calculated_at' => now()->toIso8601String(),
            ],
            'updated_by' => $request->user()->id,
        ])->save();
        DB::table('assessment_activity_logs')->insert([
            'actor_id' => $request->user()->id,
            'action' => $creating ? 'Employee backpay prepared' : 'Employee backpay updated',
            'target_type' => EmployeeBackpay::class,
            'target_id' => $backpay->id,
            'metadata' => json_encode(['employee_id' => $employee->id, 'status' => $backpay->status, 'total_cents' => $backpay->total_cents], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
