<?php

namespace App\Http\Controllers;

use App\Models\AccountingEntry;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AccountingController extends Controller
{
    public function index(Request $request, AccountingService $service)
    {
        $service->authorize($request->user());
        $filters = $request->validate(['status' => ['nullable', Rule::in(['draft', 'approved', 'paid', 'void'])], 'kind' => ['nullable', Rule::in(['payroll', 'training_allowance'])], 'search' => ['nullable', 'string', 'max:150']]);
        $query = AccountingEntry::with('employee:id,name,username')
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['kind'] ?? null, fn ($q, $v) => $q->where('kind', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($u) => $u->where(fn ($names) => $names->where('name', 'like', '%'.$v.'%')->orWhere('username', 'like', '%'.$v.'%'))));

        return Inertia::render('accounting', [
            'entries' => $query->latest()->paginate(20, ['id', 'user_id', 'kind', 'description', 'period_start', 'period_end', 'gross_cents', 'deduction_cents', 'net_cents', 'status', 'paid_at'])->withQueryString(),
            'summary' => collect(['draft', 'approved', 'paid'])->mapWithKeys(fn ($status) => [$status => (int) AccountingEntry::where('status', $status)->sum('net_cents')]),
            'employees' => User::where('role', '!=', 'trainee')->whereNotNull('username')->orderBy('name')->get(['id', 'name', 'username', 'status']),
            'enrollments' => DB::table('training_plan_enrollments as e')->join('users as u', 'u.id', '=', 'e.user_id')->join('training_plans as p', 'p.id', '=', 'e.training_plan_id')->select('u.id as user_id', 'u.name', 'u.username', 'p.id as plan_id', 'p.name as plan_name')->orderBy('u.name')->get(),
            'filters' => $filters, 'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function payroll(Request $request, AccountingService $service)
    {
        $service->authorize($request->user());
        $data = $request->validate([
            'request_key' => ['required', 'uuid'], 'user_id' => ['required', 'integer', 'exists:users,id'],
            'description' => ['required', 'string', 'max:200'], 'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'gross' => ['required', 'string', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'deduction' => ['required', 'string', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $service->payroll($request->user(), $data);

        return back()->with('status', 'Payroll draft saved for review. No payment has been recorded.');
    }

    public function preview(Request $request, TrainingPlan $plan, User $trainee, AccountingService $service)
    {
        $service->authorize($request->user());
        $data = $request->validate(['cutoff' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()]]);

        return response()->json($service->allowancePreview($plan, $trainee, $data['cutoff']))->header('Cache-Control', 'private, no-store');
    }

    public function allowance(Request $request, TrainingPlan $plan, User $trainee, AccountingService $service)
    {
        $service->authorize($request->user());
        $data = $request->validate(['request_key' => ['required', 'uuid'], 'cutoff' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()]]);
        $service->allowance($request->user(), $plan, $trainee, $data);

        return back()->with('status', 'Training allowance draft prepared from eligible attendance days. Review and approve before recording a payment.');
    }

    public function show(Request $request, AccountingEntry $entry, AccountingService $service)
    {
        $service->authorize($request->user());

        return response()->json($entry->load(['employee:id,name,username', 'creator:id,name', 'approver:id,name', 'payer:id,name', 'voider:id,name']))->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, AccountingEntry $entry, AccountingService $service)
    {
        $service->authorize($request->user());
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'pay', 'void'])],
            'paid_at' => ['required_if:action,pay', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'payment_method' => ['required_if:action,pay', 'nullable', Rule::in(['bank_transfer', 'cash', 'check', 'e_wallet'])],
            'payment_reference' => ['required_if:action,pay', 'nullable', 'string', 'max:150'],
            'void_reason' => ['required_if:action,void', 'nullable', 'string', 'min:5', 'max:2000'],
        ]);
        $service->transition($request->user(),$entry,$data);

        return back()->with('status','Accounting entry updated.');
    }
}
