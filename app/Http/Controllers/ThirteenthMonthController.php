<?php

namespace App\Http\Controllers;

use App\Models\ThirteenthMonthFundContribution;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AuditLogService;
use App\Services\ThirteenthMonthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ThirteenthMonthController extends Controller
{
    public function index(Request $request, AccountingService $accounting, ThirteenthMonthService $service): JsonResponse
    {
        $accounting->authorize($request->user());
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2020', 'max:'.now('Asia/Manila')->year],
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['active', 'floating', 'resigned', 'suspended', 'terminated'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $year = (int) ($filters['year'] ?? now('Asia/Manila')->year);
        $employees = User::query()->whereNotNull('username')->where('role', '!=', 'trainee')->orderBy('name')->get(['id', 'name', 'username', 'role', 'status']);
        $report = $service->report($year, $employees);
        $allRows = $report['rows'];
        $rows = $allRows
            ->when($filters['status'] ?? null, fn ($items, $status) => $items->where('employee.status', $status))
            ->when($filters['search'] ?? null, function ($items, $search) {
                $search = mb_strtolower($search);

                return $items->filter(fn ($row) => str_contains(mb_strtolower($row['employee']['name']), $search)
                    || str_contains(mb_strtolower((string) $row['employee']['username']), $search));
            })->values();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = 20;
        $records = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page);
        $contributions = ThirteenthMonthFundContribution::query()->where('year', $year)->whereNull('voided_at')->get();
        $funded = (int) $contributions->sum('amount_cents');
        $accrued = (int) $allRows->sum('thirteenth_month_cents');
        $fundingByMonth = $contributions->groupBy(fn ($item) => $item->contribution_month->format('Y-m'))->map->sum('amount_cents');
        $cumulativeAccrued = 0;
        $cumulativeFunded = 0;
        $monthly = collect($report['monthly'])->map(function ($month) use ($fundingByMonth, &$cumulativeAccrued, &$cumulativeFunded) {
            $month['funded_cents'] = (int) ($fundingByMonth[$month['month']] ?? 0);
            $cumulativeAccrued += $month['accrued_cents'];
            $cumulativeFunded += $month['funded_cents'];
            $month['cumulative_accrued_cents'] = $cumulativeAccrued;
            $month['cumulative_funded_cents'] = $cumulativeFunded;
            $month['balance_cents'] = max(0, $cumulativeAccrued - $cumulativeFunded);
            $month['progress_percent'] = $cumulativeAccrued > 0 ? round(min(100, $cumulativeFunded / $cumulativeAccrued * 100), 2) : 0;

            return $month;
        })->values();

        return response()->json([
            'records' => $records,
            'summary' => [
                'eligible_basic_cents' => $allRows->sum('eligible_basic_cents'),
                'confirmed_basic_cents' => $allRows->sum('confirmed_basic_cents'),
                'pending_basic_cents' => $allRows->sum('pending_basic_cents'),
                'thirteenth_month_cents' => $accrued,
                'daily_accrual_cents' => $allRows->sum('daily_accrual_cents'),
                'employees_with_accrual' => $allRows->where('thirteenth_month_cents', '>', 0)->count(),
                'incomplete_records' => $allRows->where('data_complete', false)->count(),
            ],
            'fund' => [
                'funded_cents' => $funded,
                'balance_cents' => max(0, $accrued - $funded),
                'excess_cents' => max(0, $funded - $accrued),
                'progress_percent' => $accrued > 0 ? round(min(100, $funded / $accrued * 100), 2) : 0,
                'contribution_count' => $contributions->count(),
            ],
            'monthly' => $monthly,
            'contributions' => ThirteenthMonthFundContribution::query()->where('year', $year)->with('creator:id,name')
                ->latest('contribution_month')->latest('id')->limit(24)->get()->map(fn ($item) => [
                    'id' => $item->id,
                    'contribution_month' => $item->contribution_month->format('Y-m'),
                    'amount_cents' => $item->amount_cents,
                    'reference' => $item->reference,
                    'notes' => $item->notes,
                    'voided_at' => $item->voided_at?->toISOString(),
                    'void_reason' => $item->void_reason,
                    'created_by' => $item->creator?->name,
                    'created_at' => $item->created_at?->toISOString(),
                ]),
            'year' => $year,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, AccountingService $accounting): RedirectResponse
    {
        $accounting->authorizeFinance($request->user());
        $data = $request->validate([
            'request_key' => ['required', 'uuid'],
            'contribution_month' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'string', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($data['contribution_month'] > now('Asia/Manila')->format('Y-m')) {
            throw ValidationException::withMessages(['contribution_month' => 'The contribution month cannot be in the future.']);
        }
        $amount = $accounting->money($data['amount']);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
        }
        DB::transaction(function () use ($request, $data, $amount) {
            if (ThirteenthMonthFundContribution::where('request_key', $data['request_key'])->exists()) {
                return;
            }
            $attributes = $data;
            unset($attributes['amount']);
            $contribution = ThirteenthMonthFundContribution::create([
                ...$attributes,
                'contribution_month' => $data['contribution_month'].'-01',
                'year' => (int) substr($data['contribution_month'], 0, 4),
                'amount_cents' => $amount,
                'created_by' => $request->user()->id,
            ]);
            $this->audit($request, $contribution, '13th-month fund contribution recorded', ['amount_cents' => $amount]);
        });

        return back()->with('status', 'The CEO fund contribution was recorded.');
    }

    public function void(Request $request, ThirteenthMonthFundContribution $contribution, AccountingService $accounting): RedirectResponse
    {
        $accounting->authorizeFinance($request->user());
        $data = $request->validate(['void_reason' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($request, $contribution, $data) {
            $contribution = ThirteenthMonthFundContribution::whereKey($contribution->id)->lockForUpdate()->firstOrFail();
            if ($contribution->voided_at) {
                throw ValidationException::withMessages(['void_reason' => 'This contribution is already void.']);
            }
            $contribution->update([...$data, 'voided_at' => now(), 'voided_by' => $request->user()->id]);
            $this->audit($request, $contribution, '13th-month fund contribution voided', $data);
        });

        return back()->with('status', 'The contribution was voided and removed from the active fund total.');
    }

    private function audit(Request $request, ThirteenthMonthFundContribution $contribution, string $action, array $metadata): void
    {
        app(AuditLogService::class)->record($request->user(), 'payroll', $action, $contribution, metadata: $metadata);
    }
}
