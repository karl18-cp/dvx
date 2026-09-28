<?php

namespace App\Http\Controllers;

use App\Models\AccountingExpense;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountingExpenseController extends Controller
{
    public function index(Request $request, AccountingService $service)
    {
        $service->authorize($request->user());
        $filters = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = AccountingExpense::query()
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('expense_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('expense_date', '<=', $v));
        $totals = (clone $query)->whereNull('voided_at')->selectRaw('category_id, SUM(amount_cents) as total_cents, COUNT(*) as expense_count')->groupBy('category_id')->get();
        $query->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v));

        return response()->json([
            'categories' => DB::table('expense_categories')->orderBy('name')->get(['id', 'name']),
            'totals' => $totals,
            'filtered_total_cents' => (int) (clone $query)->whereNull('voided_at')->sum('amount_cents'),
            'expenses' => $query->orderByDesc('expense_date')->orderByDesc('id')->paginate(20),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function category(Request $request, AccountingService $service, ?int $category = null)
    {
        $service->authorize($request->user());
        if ($category !== null) {
            abort_unless(DB::table('expense_categories')->where('id', $category)->exists(), 404);
        }
        $request->validate(['name' => ['required', 'string', 'max:100']]);
        $name = preg_replace('/\s+/u', ' ', trim($request->input('name')));
        $request->merge(['name' => $name, 'name_key' => mb_strtolower($name)]);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'name_key' => ['required', Rule::unique('expense_categories', 'name_key')->ignore($category)]]);
        DB::transaction(function () use ($request, $data, $category) {
            if ($category) {
                $before = DB::table('expense_categories')->where('id', $category)->lockForUpdate()->first();
                DB::table('expense_categories')->where('id', $category)->update([...$data, 'updated_at' => now()]);
                $id = $category;
            } else {
                $before = null;
                $id = DB::table('expense_categories')->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->audit($request, 'Expense category saved', 'expense_category', $id, ['before' => $before, 'name' => $data['name']]);
        });

        return back();
    }

    public function store(Request $request, AccountingService $service)
    {
        $service->authorize($request->user());
        $data = $request->validate([
            'request_key' => ['required', 'uuid'], 'category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'description' => ['required', 'string', 'max:200'], 'payee' => ['nullable', 'string', 'max:200'],
            'amount' => ['required', 'string', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'reference' => ['nullable', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $amount = $service->money($data['amount']);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
        }
        unset($data['amount']);
        DB::transaction(function () use ($data, $amount, $request) {
            $expense = AccountingExpense::firstOrCreate(['request_key' => $data['request_key']], [...$data, 'amount_cents' => $amount, 'created_by' => $request->user()->id]);
            if ($expense->wasRecentlyCreated) {
                $this->audit($request, 'Expense recorded', AccountingExpense::class, $expense->id, $expense->toArray());
            }
        });

        return back();
    }

    public function void(Request $request, AccountingExpense $expense, AccountingService $service)
    {
        $service->authorize($request->user());
        $data = $request->validate(['void_reason' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($request, $expense, $data) {
            $expense = AccountingExpense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($expense->voided_at) {
                throw ValidationException::withMessages(['void_reason' => 'This expense is already void.']);
            }
            $expense->update([...$data, 'voided_at' => now(), 'voided_by' => $request->user()->id]);
            $this->audit($request, 'Expense voided', AccountingExpense::class, $expense->id, $expense->toArray());
        });

        return back();
    }

    private function audit(Request $request, string $action, string $type, int $id, array $metadata): void
    {
        DB::table('assessment_activity_logs')->insert(['actor_id' => $request->user()->id, 'action' => $action, 'target_type' => $type, 'target_id' => $id, 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
