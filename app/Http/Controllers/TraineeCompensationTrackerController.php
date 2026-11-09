<?php

namespace App\Http\Controllers;

use App\Models\AccountingEntry;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\TrainingAllowanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class TraineeCompensationTrackerController extends Controller
{
    public function index(Request $request, AccountingService $accounting, TrainingAllowanceService $allowances): JsonResponse
    {
        $accounting->authorize($request->user());
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['in_training', 'graduated', 'rejected'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $enrollments = TrainingPlan::query()
            ->with([
                'campaign:id,name',
                'trainees' => fn ($query) => $query
                    ->where('role', 'trainee')
                    ->when($filters['status'] ?? null, fn ($statusQuery, $status) => $statusQuery->where('training_status', $status))
                    ->when($filters['search'] ?? null, fn ($searchQuery, $search) => $searchQuery->where(
                        fn ($match) => $match->where('name', 'like', '%'.$search.'%')->orWhere('username', 'like', '%'.$search.'%')
                    ))
                    ->orderBy('name'),
            ])
            ->orderByDesc('start_date')
            ->get();

        $traineeIds = $enrollments->flatMap->trainees->pluck('id')->unique();
        $entries = AccountingEntry::query()
            ->whereIn('user_id', $traineeIds)
            ->where('kind', 'training_allowance')
            ->where('status', '!=', 'void')
            ->get(['user_id', 'training_plan_id', 'net_cents', 'status'])
            ->groupBy(fn (AccountingEntry $entry) => $entry->training_plan_id.':'.$entry->user_id);

        $rows = $enrollments->flatMap(function (TrainingPlan $plan) use ($allowances, $entries) {
            return $plan->trainees->map(function (User $trainee) use ($plan, $allowances, $entries) {
                $summary = $allowances->summary($plan, $trainee);
                $payments = $entries->get($plan->id.':'.$trainee->id, collect());
                $paid = (int) $payments->where('status', 'paid')->sum('net_cents');
                $reserved = (int) $payments->whereIn('status', ['draft', 'approved'])->sum('net_cents');
                $outstanding = max(0, $summary['earned_cents'] - $paid - $reserved);
                $today = CarbonImmutable::now('Asia/Manila')->toDateString();
                $reviewed = $trainee->training_reviewed_at
                    ? CarbonImmutable::parse($trainee->training_reviewed_at)->setTimezone('Asia/Manila')->toDateString()
                    : null;
                $trackingEnd = $reviewed && $reviewed < $today ? $reviewed : $today;
                $expected = collect($summary['rows'])->where('date', '<=', $trackingEnd)->count();
                $attended = $summary['qualified_days'];
                $totalDays = count($summary['rows']);
                $available = $summary['first_allowance_ready'] ? $outstanding : 0;

                return [
                    'trainee' => $summary['trainee'],
                    'plan' => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'campaign' => $plan->campaign?->name,
                        'start_date' => $plan->start_date->toDateString(),
                        'first_allowance_day' => $plan->first_allowance_day,
                    ],
                    'attendance' => [
                        'attended_days' => $attended,
                        'expected_days' => $expected,
                        'missed_days' => max(0, $expected - $attended),
                        'upcoming_days' => max(0, $totalDays - $expected),
                        'total_days' => $totalDays,
                        'worked_minutes' => (int) collect($summary['rows'])->where('eligible', true)->sum('worked_minutes'),
                        'rate_percent' => $expected > 0 ? round($attended / $expected * 100, 2) : 0,
                        'progress_percent' => $totalDays > 0 ? round($attended / $totalDays * 100, 2) : 0,
                    ],
                    'salary' => [
                        'earned_cents' => $summary['earned_cents'],
                        'paid_cents' => $paid,
                        'reserved_cents' => $reserved,
                        'outstanding_cents' => $outstanding,
                        'available_cents' => $available,
                        'projected_total_cents' => $summary['projected_total_cents'],
                        'first_allowance_cents' => $summary['first_allowance_cents'],
                        'first_allowance_ready' => $summary['first_allowance_ready'],
                        'first_allowance_date' => $summary['first_allowance_date'],
                        'payout_status' => $reserved > 0
                            ? 'Pending accounting entry'
                            : ($available > 0
                                ? 'Ready for payout'
                                : ($summary['first_allowance_ready'] ? 'Up to date' : 'Building first payout')),
                    ],
                ];
            });
        })->values();

        $page = (int) ($filters['page'] ?? 1);
        $perPage = 20;
        $records = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page
        );

        return response()->json([
            'records' => $records,
            'summary' => [
                'trainees' => $rows->count(),
                'attended_days' => $rows->sum('attendance.attended_days'),
                'earned_cents' => $rows->sum('salary.earned_cents'),
                'paid_cents' => $rows->sum('salary.paid_cents'),
                'reserved_cents' => $rows->sum('salary.reserved_cents'),
                'outstanding_cents' => $rows->sum('salary.outstanding_cents'),
                'available_cents' => $rows->sum('salary.available_cents'),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(
        Request $request,
        TrainingPlan $plan,
        User $trainee,
        AccountingService $accounting,
        TrainingAllowanceService $allowances
    ): JsonResponse {
        $accounting->authorize($request->user());
        abort_unless($trainee->role === 'trainee' && $plan->trainees()->whereKey($trainee->id)->exists(), 404);

        $summary = $allowances->summary($plan, $trainee);
        $entries = AccountingEntry::query()
            ->where('user_id', $trainee->id)
            ->where('training_plan_id', $plan->id)
            ->where('kind', 'training_allowance')
            ->where('status', '!=', 'void')
            ->get();
        $paid = (int) $entries->where('status', 'paid')->sum('net_cents');
        $reserved = (int) $entries->whereIn('status', ['draft', 'approved'])->sum('net_cents');

        return response()->json([
            'allowance' => $summary,
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'first_allowance_day' => $plan->first_allowance_day,
            ],
            'salary' => [
                'paid_cents' => $paid,
                'reserved_cents' => $reserved,
                'outstanding_cents' => max(0, $summary['earned_cents'] - $paid - $reserved),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
