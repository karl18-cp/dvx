<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountingService;
use App\Services\ThirteenthMonthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

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
        $employees = User::query()->whereNotNull('username')->where('role', '!=', 'trainee')
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($match) => $match->where('name', 'like', '%'.$value.'%')->orWhere('username', 'like', '%'.$value.'%')))
            ->orderBy('name')->get(['id', 'name', 'username', 'role', 'status']);
        $rows = $service->rows($year, $employees);
        $page = (int) ($filters['page'] ?? 1);
        $perPage = 20;
        $records = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page);

        return response()->json([
            'records' => $records,
            'summary' => [
                'eligible_basic_cents' => $rows->sum('eligible_basic_cents'),
                'thirteenth_month_cents' => $rows->sum('thirteenth_month_cents'),
                'employees_with_accrual' => $rows->where('thirteenth_month_cents', '>', 0)->count(),
                'incomplete_records' => $rows->where('data_complete', false)->count(),
            ],
            'year' => $year,
        ])->header('Cache-Control', 'private, no-store');
    }
}
