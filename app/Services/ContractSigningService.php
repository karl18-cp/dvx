<?php

namespace App\Services;

use App\Models\EmployeeContract;
use App\Models\EmployeeContractSignature;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContractSigningService
{
    public const CONSENT = 'I have reviewed this exact contract version, agree to use an electronic signature, and understand that my electronic signature has the same intent as signing a paper copy.';

    public function issue(EmployeeContract $contract, User $actor, ?string $dueAt): EmployeeContract
    {
        abort_unless($actor->hasPermission('finance.manage'), 403);
        if ($contract->signing_status !== 'draft' || $contract->archived_at) {
            throw ValidationException::withMessages(['contract' => 'Only a current draft contract can be issued for signature.']);
        }
        $disk = Storage::disk($contract->file_disk);
        abort_unless($disk->exists($contract->file_path), 404);
        $hash = hash_file('sha256', $disk->path($contract->file_path));
        EmployeeContract::query()
            ->where('version_group', $contract->version_group)
            ->whereKeyNot($contract->id)
            ->where('signing_status', 'issued')
            ->update([
                'signing_status' => 'superseded',
                'status' => 'superseded',
                'updated_at' => now(),
            ]);
        $contract->update([
            'document_sha256' => $hash, 'signing_status' => 'issued', 'issued_by' => $actor->id,
            'issued_at' => now(), 'signature_due_at' => $dueAt, 'immutable_at' => now(),
        ]);

        return $contract->fresh();
    }

    public function sign(EmployeeContract $contract, User $signer, string $signatureName, ?string $ip, ?string $userAgent): EmployeeContractSignature
    {
        $storedPath = null;
        try {
            return DB::transaction(function () use ($contract, $signer, $signatureName, $ip, $userAgent, &$storedPath) {
                $contract = EmployeeContract::query()->lockForUpdate()->with('employee')->findOrFail($contract->id);
                abort_unless($contract->user_id === $signer->id, 403);
                if ($contract->signing_status !== 'issued' || $contract->signature) {
                    throw ValidationException::withMessages(['contract' => 'This contract is not available for signature.']);
                }
                if ($contract->signature_due_at && $contract->signature_due_at->isPast()) {
                    throw ValidationException::withMessages(['contract' => 'The signature deadline has passed. Ask an administrator to issue a new version.']);
                }
                if (mb_strtolower(trim($signatureName)) !== mb_strtolower(trim($signer->name))) {
                    throw ValidationException::withMessages(['signature_name' => 'Type your full account name exactly as shown.']);
                }
                $disk = Storage::disk($contract->file_disk);
                abort_unless($disk->exists($contract->file_path), 404);
                $documentHash = hash_file('sha256', $disk->path($contract->file_path));
                if (! hash_equals((string) $contract->document_sha256, $documentHash)) {
                    throw ValidationException::withMessages(['contract' => 'The source document no longer matches the issued version. Contact an administrator.']);
                }
                $signedAt = now();
                $canonical = implode('|', [$contract->id, $contract->version, $signer->id, $signatureName, $documentHash, $signedAt->toIso8601String(), $ip]);
                $signatureHash = hash_hmac('sha256', $canonical, (string) config('app.key'));
                $signature = EmployeeContractSignature::create([
                    'employee_contract_id' => $contract->id, 'signer_id' => $signer->id,
                    'signer_name' => $signer->name, 'signer_username' => $signer->username, 'signer_email' => $signer->email,
                    'signer_role' => $signer->role, 'signature_name' => $signatureName, 'consent_text' => self::CONSENT,
                    'document_sha256' => $documentHash, 'signature_hash' => $signatureHash,
                    'ip_address' => $ip, 'user_agent' => mb_substr((string) $userAgent, 0, 500), 'signed_at' => $signedAt,
                ]);
                $pdf = $this->renderCertificate($contract, $signature);
                $storedPath = "signed-contracts/{$signer->id}/contract-{$contract->id}-v{$contract->version}-".substr($signatureHash, 0, 12).'.pdf';
                throw_unless(Storage::disk('local')->put($storedPath, $pdf), new \RuntimeException('The signed PDF could not be stored.'));
                $contract->update(['signing_status' => 'signed', 'signed_pdf_disk' => 'local', 'signed_pdf_path' => $storedPath, 'signed_pdf_sha256' => hash('sha256', $pdf), 'status' => 'active']);

                return $signature;
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }
    }

    public function renderCertificate(EmployeeContract $contract, EmployeeContractSignature $signature): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $logo = public_path('images/divertex-logo.png');
        $contents = is_file($logo) ? file_get_contents($logo) : false;
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.signed-contract', [
            'contract' => $contract, 'signature' => $signature,
            'logoDataUri' => is_string($contents) ? 'data:image/png;base64,'.base64_encode($contents) : null,
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
