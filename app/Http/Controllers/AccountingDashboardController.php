<?php

namespace App\Http\Controllers;

use App\Models\AccountingEntry;
use App\Models\EmployeeBackpay;
use App\Models\EmployeeContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AccountingDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor?->role === 'accounting' && $actor->canAccessAccount(), 403);

        $today = now('Asia/Manila')->startOfDay();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd = $today->copy()->endOfMonth()->toDateString();
        $paymentTotals = DB::table('accounting_document_payments')
            ->selectRaw('document_id, SUM(amount_cents) as paid_cents')
            ->groupBy('document_id');
        $documents = DB::table('accounting_documents')
            ->leftJoinSub($paymentTotals, 'payment_totals', 'payment_totals.document_id', '=', 'accounting_documents.id')
            ->whereNull('accounting_documents.voided_at')
            ->select('accounting_documents.*')
            ->selectRaw('COALESCE(payment_totals.paid_cents, 0) as paid_cents')
            ->selectRaw('accounting_documents.amount_cents - COALESCE(payment_totals.paid_cents, 0) as balance_cents');

        $balances = (clone $documents)
            ->whereRaw('accounting_documents.amount_cents > COALESCE(payment_totals.paid_cents, 0)')
            ->get()
            ->groupBy('direction');
        $receivableBalance = (int) $balances->get('receivable', collect())->sum('balance_cents');
        $payableBalance = (int) $balances->get('payable', collect())->sum('balance_cents');

        $pendingEntries = AccountingEntry::query()->whereIn('status', ['draft', 'approved']);
        $monthPayroll = AccountingEntry::query()
            ->where('kind', 'payroll')->where('status', 'paid')
            ->whereBetween('paid_at', [$monthStart.' 00:00:00', $monthEnd.' 23:59:59']);

        $monthDocumentPayments = DB::table('accounting_document_payments as payments')
            ->join('accounting_documents as documents', 'documents.id', '=', 'payments.document_id')
            ->whereNull('documents.voided_at')
            ->whereBetween('payments.payment_date', [$monthStart, $monthEnd]);

        $priorities = [
            'draft_payrolls' => AccountingEntry::where('kind', 'payroll')->where('status', 'draft')->count(),
            'approved_for_payment' => AccountingEntry::where('status', 'approved')->count(),
            'overdue_receivables' => $balances->get('receivable', collect())->where('due_date', '<', $today->toDateString())->count(),
            'overdue_payables' => $balances->get('payable', collect())->where('due_date', '<', $today->toDateString())->count(),
            'backpays_waiting' => EmployeeBackpay::whereIn('status', ['pending_clearance', 'ready'])->count(),
            'contracts_expiring' => EmployeeContract::whereNull('archived_at')->whereNotNull('end_date')
                ->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])->count(),
        ];

        $upcoming = (clone $documents)
            ->whereRaw('accounting_documents.amount_cents > COALESCE(payment_totals.paid_cents, 0)')
            ->orderBy('accounting_documents.due_date')->limit(8)->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'direction' => $row->direction,
                'party_name' => $row->party_name,
                'description' => $row->description,
                'due_date' => $row->due_date,
                'balance_cents' => (int) $row->balance_cents,
                'overdue' => $row->due_date < $today->toDateString(),
            ]);

        $recentEntries = AccountingEntry::with('employee:id,name')->latest('updated_at')->limit(5)->get()->map(fn ($entry) => [
            'key' => 'entry-'.$entry->id,
            'title' => $entry->description,
            'detail' => ($entry->employee?->name ?? 'Employee').' · '.str_replace('_', ' ', $entry->status),
            'amount_cents' => $entry->net_cents,
            'occurred_at' => $entry->updated_at?->toIso8601String(),
            'href' => '/accounting/workspace/'.($entry->kind === 'training_allowance' ? 'training-allowances' : 'payment-register'),
        ]);
        $recentExpenses = DB::table('accounting_expenses')->whereNull('voided_at')->latest('updated_at')->limit(5)->get()->map(fn ($expense) => [
            'key' => 'expense-'.$expense->id,
            'title' => $expense->description,
            'detail' => ($expense->payee ?: 'Expense').' · recorded',
            'amount_cents' => (int) $expense->amount_cents,
            'occurred_at' => $expense->updated_at,
            'href' => '/accounting/workspace/expenses',
        ]);

        return Inertia::render('accounting-dashboard', [
            'asOf' => $today->toDateString(),
            'metrics' => [
                'payroll_paid_cents' => (int) (clone $monthPayroll)->sum('net_cents'),
                'payroll_paid_count' => (clone $monthPayroll)->count(),
                'pending_cents' => (int) (clone $pendingEntries)->sum('net_cents'),
                'pending_count' => (clone $pendingEntries)->count(),
                'receivable_balance_cents' => $receivableBalance,
                'receivable_count' => $balances->get('receivable', collect())->count(),
                'payable_balance_cents' => $payableBalance,
                'payable_count' => $balances->get('payable', collect())->count(),
            ],
            'monthActivity' => [
                'receipts_cents' => (int) (clone $monthDocumentPayments)->where('documents.direction', 'receivable')->sum('payments.amount_cents'),
                'supplier_payments_cents' => (int) (clone $monthDocumentPayments)->where('documents.direction', 'payable')->sum('payments.amount_cents'),
                'expenses_cents' => (int) DB::table('accounting_expenses')->whereNull('voided_at')->whereBetween('expense_date', [$monthStart, $monthEnd])->sum('amount_cents'),
                'payroll_cents' => (int) (clone $monthPayroll)->sum('net_cents'),
            ],
            'priorities' => $priorities,
            'upcoming' => $upcoming,
            'recentActivity' => $recentEntries->concat($recentExpenses)->sortByDesc('occurred_at')->take(7)->values(),
        ])->withViewData(['cacheControl' => 'private, no-store']);
    }
}
