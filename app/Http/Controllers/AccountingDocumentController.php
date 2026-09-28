<?php

namespace App\Http\Controllers;

use App\Models\AccountingDocument;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountingDocumentController extends Controller
{
    private function query()
    {
        $payments = DB::table('accounting_document_payments')->selectRaw('document_id, SUM(amount_cents) as paid')->groupBy('document_id');

        return AccountingDocument::query()->leftJoinSub($payments, 'payments', 'payments.document_id', '=', 'accounting_documents.id')->select('accounting_documents.*')->selectRaw('COALESCE(payments.paid, 0) as paid_cents');
    }

    private function present(AccountingDocument $document): array
    {
        $paid = (int) $document->paid_cents;
        $balance = $document->voided_at ? 0 : $document->amount_cents - $paid;
        $status = $document->voided_at ? 'void' : ($balance === 0 ? 'settled' : ($document->due_date->toDateString() < now('Asia/Manila')->toDateString() ? 'overdue' : ($paid > 0 ? 'partial' : 'open')));

        return [...$document->toArray(), 'paid_cents' => $paid, 'balance_cents' => $balance, 'status' => $status];
    }

    public function index(Request $request, AccountingService $service)
    {
        $service->authorize($request->user());
        $filters = $request->validate(['view' => ['required', Rule::in(['invoices', 'receivable', 'payable'])], 'search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(['open', 'partial', 'settled', 'overdue', 'void'])], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = $this->query();
        if ($filters['view'] === 'invoices') {
            $query->whereNotNull('invoice_number');
        } else {
            $query->where('direction', $filters['view']);
        }
        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($names) => $names->where('party_name', 'like', '%'.$v.'%')->orWhere('invoice_number', 'like', '%'.$v.'%')->orWhere('description', 'like', '%'.$v.'%')));
        $status = $filters['status'] ?? null;
        $today = now('Asia/Manila')->toDateString();
        if ($status === 'void') {
            $query->whereNotNull('voided_at');
        } elseif ($status) {
            $query->whereNull('voided_at');
            if ($status === 'settled') {
                $query->whereRaw('amount_cents = COALESCE(payments.paid, 0)');
            } else {
                $query->whereRaw('amount_cents > COALESCE(payments.paid, 0)');
                if ($status === 'overdue') {
                    $query->whereDate('due_date', '<', $today);
                } else {
                    $query->whereDate('due_date', '>=', $today);
                    $query->whereRaw($status === 'partial' ? 'COALESCE(payments.paid, 0) > 0' : 'COALESCE(payments.paid, 0) = 0');
                }
            }
        }
        $summary = (clone $query)->whereNull('voided_at')->select([])->selectRaw('COALESCE(SUM(amount_cents),0) as total_cents, COALESCE(SUM(COALESCE(payments.paid,0)),0) as paid_cents, COALESCE(SUM(amount_cents - COALESCE(payments.paid,0)),0) as balance_cents')->first();

        return response()->json(['records' => $query->orderByDesc('accounting_documents.id')->paginate(20)->through(fn ($row) => $this->present($row)), 'summary' => ['total_cents' => (int) $summary->total_cents, 'paid_cents' => (int) $summary->paid_cents, 'balance_cents' => (int) $summary->balance_cents]])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, AccountingService $service)
    {
        $service->authorize($request->user());
        $data = $request->validate([
            'record_type' => ['required', Rule::in(['invoices', 'receivable', 'payable'])],
            'request_key' => ['required', 'uuid'], 'direction' => ['required', Rule::in(['receivable', 'payable'])],
            'invoice_number' => ['required_if:record_type,invoices', 'nullable', 'string', 'max:100'], 'party_name' => ['required', 'string', 'max:200'], 'party_email' => ['nullable', 'email', 'max:200'],
            'description' => ['required', 'string', 'max:250'], 'issue_date' => ['required', 'date_format:Y-m-d'], 'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
            'amount' => ['required', 'string', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'notes' => ['nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $amount = $service->money($data['amount']);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
        }
        $data['invoice_key'] = empty($data['invoice_number']) ? null : hash('sha256', $data['direction'].'|'.mb_strtolower(trim($data['party_name'])).'|'.mb_strtolower(trim($data['invoice_number'])));
        unset($data['amount'], $data['attachment'], $data['record_type']);
        $storedPath = null;
        try {
            DB::transaction(function () use ($request, $data, $amount, &$storedPath) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                if (AccountingDocument::where('request_key', $data['request_key'])->exists()) {
                    return;
                }
                if ($data['invoice_key'] && AccountingDocument::where('invoice_key', $data['invoice_key'])->whereNull('voided_at')->exists()) {
                    throw ValidationException::withMessages(['invoice_number' => 'This invoice is already recorded for this client or supplier.']);
                }
                $file = $request->file('attachment');
                if ($file) {
                    $storedPath = $file->store('accounting-documents', 'local');
                    if (! $storedPath) {
                        throw new \RuntimeException('The attachment could not be saved.');
                    }
                }
                $document = AccountingDocument::create([...$data, 'amount_cents' => $amount, 'created_by' => $request->user()->id, 'attachment_path' => $storedPath, 'attachment_name' => $file ? mb_substr(basename($file->getClientOriginalName()), 0, 200) : null]);
                $this->audit($request, $document, 'Accounting document recorded', ['direction' => $document->direction, 'amount_cents' => $amount]);
            });
        } catch (\Throwable $error) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            if ($error instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['invoice_number' => 'This invoice or submission has already been recorded. Refresh and review the existing record.']);
            }
            throw $error;
        }

        return back();
    }

    public function show(Request $request, AccountingDocument $document, AccountingService $service)
    {
        $service->authorize($request->user());

        return response()->json([...$this->present($this->query()->where('accounting_documents.id', $document->id)->firstOrFail()), 'payments' => DB::table('accounting_document_payments')->where('document_id', $document->id)->orderByDesc('id')->get(['id', 'amount_cents', 'payment_date', 'method', 'reference'])])->header('Cache-Control', 'private, no-store');
    }

    public function payment(Request $request, AccountingDocument $document, AccountingService $service)
    {
        $service->authorize($request->user());
        $data = $request->validate(['request_key' => ['required', 'uuid'], 'amount' => ['required', 'string', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()], 'method' => ['required', Rule::in(['bank_transfer', 'cash', 'check', 'e_wallet'])], 'reference' => ['required', 'string', 'max:150']]);
        $amount = $service->money($data['amount']);
        unset($data['amount']);
        DB::transaction(function () use ($request, $document, $data, $amount) {
            $document = AccountingDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if (DB::table('accounting_document_payments')->where('request_key', $data['request_key'])->exists()) {
                return;
            }
            if ($document->voided_at) {
                throw ValidationException::withMessages(['amount' => 'A void record cannot receive payments.']);
            }
            $paid = (int) DB::table('accounting_document_payments')->where('document_id', $document->id)->sum('amount_cents');
            if ($amount <= 0 || $amount > $document->amount_cents - $paid) {
                throw ValidationException::withMessages(['amount' => 'Enter a positive amount no greater than the outstanding balance.']);
            }
            if ($data['payment_date'] < $document->issue_date->toDateString()) {
                throw ValidationException::withMessages(['payment_date' => 'Payment date cannot precede the issue date.']);
            }
            DB::table('accounting_document_payments')->insert([...$data, 'document_id' => $document->id, 'amount_cents' => $amount, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($request, $document, 'Document payment recorded', ['amount_cents' => $amount, 'reference' => $data['reference']]);
        });

        return back();
    }

    public function void(Request $request, AccountingDocument $document, AccountingService $service)
    {
        $service->authorize($request->user());
        $data = $request->validate(['void_reason' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($request, $document, $data) {
            $document = AccountingDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($document->voided_at || DB::table('accounting_document_payments')->where('document_id', $document->id)->exists()) {
                throw ValidationException::withMessages(['void_reason' => 'Only an unpaid, non-void record can be voided.']);
            }
            $document->update([...$data, 'invoice_key' => null, 'voided_at' => now(), 'voided_by' => $request->user()->id]);
            $this->audit($request, $document, 'Accounting document voided', $data);
        });

        return back();
    }

    public function download(Request $request, AccountingDocument $document, AccountingService $service)
    {
        $service->authorize($request->user());
        abort_unless($document->attachment_path && Storage::disk('local')->exists($document->attachment_path), 404);

        return Storage::disk('local')->download($document->attachment_path, $document->attachment_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function audit(Request $request, AccountingDocument $document, string $action, array $metadata): void
    {
        DB::table('assessment_activity_logs')->insert(['actor_id' => $request->user()->id, 'action' => $action, 'target_type' => AccountingDocument::class, 'target_id' => $document->id, 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
