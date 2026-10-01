<?php

namespace App\Http\Controllers;

use App\Models\AccountingEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MyPayslipController extends Controller
{
    private function entries(Request $request)
    {
        abort_unless($request->user()->canAccessAccount() && $request->user()->role !== 'trainee', 403);

        return AccountingEntry::where('user_id', $request->user()->id)->where('kind', 'payroll')->where('status', 'paid');
    }

    public function index(Request $request)
    {
        $data = $request->validate(['entry' => ['nullable', 'integer', 'min:1']]);
        $entries = $this->entries($request);
        $selected = isset($data['entry']) ? (clone $entries)->whereKey($data['entry'])->firstOrFail()->id : null;

        return Inertia::render('my-payslips', [
            'payslips' => $entries->orderByDesc('paid_at')->orderByDesc('id')->paginate(12, [
                'id', 'description', 'period_start', 'period_end', 'gross_cents', 'deduction_cents', 'net_cents', 'currency', 'paid_at',
            ]),
            'selectedEntry' => $selected,
        ])->toResponse($request)->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, int $entry)
    {
        // Resolve within the current user's paid payroll, not a globally bound record.
        $record = $this->entries($request)->whereKey($entry)->firstOrFail();
        $snapshot = $record->source_snapshot ?? [];
        $payroll = $snapshot['payroll'] ?? null;
        $lines = fn ($rows, $keys) => array_map(fn ($row) => array_intersect_key($row, array_flip($keys)), $rows);

        return response()->json([
            'id' => $record->id, 'description' => $record->description,
            'employee_name' => $snapshot['employee_name'] ?? $request->user()->name,
            'employee_id' => $snapshot['employee_id'] ?? $request->user()->username,
            'period_start' => $record->period_start->toDateString(), 'period_end' => $record->period_end->toDateString(),
            'paid_at' => $record->paid_at?->toDateString(), 'scheduled_pay_date' => $payroll['pay_date'] ?? null,
            'gross_cents' => $record->gross_cents, 'deduction_cents' => $record->deduction_cents, 'net_cents' => $record->net_cents,
            'currency' => $record->currency, 'payment_method' => $record->payment_method, 'payment_reference' => $record->payment_reference,
            'earnings' => $payroll ? $lines($payroll['earnings'], ['label', 'rate', 'hours', 'amount_cents']) : null,
            'deductions' => $payroll ? $lines($payroll['deductions'], ['label', 'amount_cents']) : null,
            'attendance' => $lines($payroll['attendance']['rows'] ?? [], ['date', 'status', 'worked_minutes', 'leave_minutes', 'total_minutes']),
        ])->header('Cache-Control', 'private, no-store');
    }
}
