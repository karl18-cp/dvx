<?php

namespace App\Http\Controllers;

use App\Models\EmployeeContract;
use App\Models\User;
use App\Services\AssessmentNotificationService;
use App\Services\AuditLogService;
use App\Services\ContractSigningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DigitalContractController extends Controller
{
    public function index(Request $request)
    {
        $actor = $this->actor($request);
        $contracts = EmployeeContract::query()->with(['signature:id,employee_contract_id,signer_name,signed_at,signature_hash'])
            ->where('user_id', $actor->id)->whereNull('archived_at')->whereIn('signing_status', ['issued', 'signed', 'declined'])
            ->latest('issued_at')->get()->map(fn (EmployeeContract $contract) => [
                ...$contract->only(['id', 'title', 'contract_type', 'reference_number', 'effective_date', 'end_date', 'original_filename', 'mime_type', 'file_size', 'version', 'signing_status', 'signature_due_at', 'decline_reason']),
                'issued_at' => $contract->issued_at?->toIso8601String(), 'signed_at' => $contract->signature?->signed_at?->toIso8601String(),
                'signature_hash' => $contract->signature?->signature_hash, 'signed_pdf_available' => (bool) $contract->signed_pdf_path,
            ]);

        return Inertia::render('my-contracts', ['contracts' => $contracts, 'signerName' => $actor->name, 'consentText' => ContractSigningService::CONSENT]);
    }

    public function issue(Request $request, EmployeeContract $contract, ContractSigningService $signing, AuditLogService $audit, AssessmentNotificationService $notifications)
    {
        $actor = $this->manager($request);
        $data = $request->validate(['signature_due_at' => ['nullable', 'date', 'after:now']]);
        $contract = DB::transaction(fn () => $signing->issue(EmployeeContract::query()->lockForUpdate()->findOrFail($contract->id), $actor, $data['signature_due_at'] ?? null));
        $audit->record($actor, 'contracts', 'Contract issued for digital signature', $contract, metadata: ['employee_id' => $contract->user_id, 'version' => $contract->version, 'document_sha256' => $contract->document_sha256, 'signature_due_at' => $contract->signature_due_at?->toIso8601String()], subjectId: $contract->user_id);
        $notifications->createForUser($contract->employee, "contract:{$contract->id}:issued", 'contract', 'Contract ready for your signature', "{$contract->title} version {$contract->version} is ready for review and signature.", '/my-contracts');

        return back()->with('status', 'Contract version issued for signature.');
    }

    public function version(Request $request, EmployeeContract $contract, AuditLogService $audit)
    {
        $actor = $this->manager($request);
        $data = $request->validate(['document' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'], 'notes' => ['nullable', 'string', 'max:3000']]);
        $storedPath = null;
        try {
            $file = $request->file('document');
            $storedPath = $file->store('employee-contracts/'.$contract->user_id, 'local');
            throw_if(! $storedPath, new \RuntimeException('The new contract version could not be stored.'));
            $version = DB::transaction(function () use ($actor, $audit, $contract, $data, $file, $storedPath) {
                User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $next = (int) EmployeeContract::query()->where('version_group', $contract->version_group)->max('version') + 1;
                $version = EmployeeContract::create([
                    'request_key' => Str::uuid(), 'version_group' => $contract->version_group, 'version' => $next,
                    'user_id' => $contract->user_id, 'contract_type' => $contract->contract_type, 'title' => $contract->title,
                    'reference_number' => $contract->reference_number, 'effective_date' => $contract->effective_date,
                    'end_date' => $contract->end_date, 'status' => 'draft', 'signing_status' => 'draft',
                    'notes' => $data['notes'] ?? $contract->notes, 'file_disk' => 'local', 'file_path' => $storedPath,
                    'original_filename' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 200),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'file_size' => $file->getSize(),
                    'document_sha256' => hash_file('sha256', Storage::disk('local')->path($storedPath)), 'created_by' => $actor->id,
                ]);
                $audit->record($actor, 'contracts', 'New contract version created', $version, metadata: ['previous_contract_id' => $contract->id, 'version' => $next, 'employee_id' => $contract->user_id], subjectId: $contract->user_id);

                return $version;
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }

        return back()->with('status', "Contract version {$version->version} created as a draft.");
    }

    public function sign(Request $request, EmployeeContract $contract, ContractSigningService $signing, AuditLogService $audit, AssessmentNotificationService $notifications)
    {
        $actor = $this->actor($request);
        $data = $request->validate(['signature_name' => ['required', 'string', 'max:255'], 'consent' => ['accepted']]);
        $signature = $signing->sign($contract, $actor, $data['signature_name'], $request->ip(), $request->userAgent());
        $audit->record($actor, 'contracts', 'Contract digitally signed', $contract, metadata: ['version' => $contract->version, 'signature_hash' => $signature->signature_hash, 'document_sha256' => $signature->document_sha256], subjectId: $actor->id);
        User::query()->where('status', 'active')->whereIn('role', ['admin', 'accounting'])->get()->filter(fn (User $user) => $user->hasPermission('finance.manage'))->each(fn (User $user) => $notifications->createForUser($user, "contract:{$contract->id}:signed:{$user->id}", 'contract', 'Contract digitally signed', "{$actor->name} signed {$contract->title} version {$contract->version}.", '/accounting/workspace/contracts'));

        return back()->with('success', 'Contract signed. Your signed PDF is ready to download.');
    }

    public function decline(Request $request, EmployeeContract $contract, AuditLogService $audit)
    {
        $actor = $this->actor($request);
        abort_unless($contract->user_id === $actor->id && $contract->signing_status === 'issued', 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $contract->update(['signing_status' => 'declined', 'declined_at' => now(), 'decline_reason' => $data['reason']]);
        $audit->record($actor, 'contracts', 'Contract signature declined', $contract, metadata: ['version' => $contract->version, 'reason' => $data['reason']], subjectId: $actor->id);

        return back()->with('success', 'The contract was declined and returned for review.');
    }

    public function original(Request $request, EmployeeContract $contract)
    {
        $actor = $this->actor($request);
        abort_unless($contract->user_id === $actor->id || $actor->hasPermission('finance.manage'), 403);
        abort_unless($contract->file_path && Storage::disk($contract->file_disk)->exists($contract->file_path), 404);

        if ($request->boolean('inline') && ($contract->mime_type === 'application/pdf' || str_starts_with($contract->mime_type, 'image/'))) {
            return response()->file(Storage::disk($contract->file_disk)->path($contract->file_path), ['Content-Type' => $contract->mime_type, 'Content-Disposition' => 'inline', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        }

        return Storage::disk($contract->file_disk)->download($contract->file_path, $contract->original_filename, ['Content-Type' => $contract->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function signed(Request $request, EmployeeContract $contract): StreamedResponse
    {
        $actor = $this->actor($request);
        abort_unless($contract->user_id === $actor->id || $actor->hasPermission('finance.manage'), 403);
        abort_unless($contract->signing_status === 'signed' && $contract->signed_pdf_path && Storage::disk($contract->signed_pdf_disk)->exists($contract->signed_pdf_path), 404);

        return Storage::disk($contract->signed_pdf_disk)->download($contract->signed_pdf_path, 'Divertex-Signed-Contract-'.$contract->id.'-v'.$contract->version.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user()->fresh();
        abort_unless($actor->canAccessAccount(), 403);

        return $actor;
    }

    private function manager(Request $request): User
    {
        $actor = $this->actor($request);
        abort_unless($actor->hasPermission('finance.manage'), 403);

        return $actor;
    }
}
