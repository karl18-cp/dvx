<!doctype html>
<html><head><meta charset="utf-8"><style>
    @page { margin: 34px 42px; } body { font-family: DejaVu Sans, sans-serif; color:#17131e; font-size:12px; }
    .top { border-bottom:3px solid #b71920; padding-bottom:18px; } .logo { width:145px; } h1 { font-size:25px; margin:30px 0 8px; }
    .muted { color:#667085; } .panel { margin-top:24px; border:1px solid #e5e7eb; border-radius:10px; padding:18px; }
    table { width:100%; border-collapse:collapse; } td { padding:8px 6px; border-bottom:1px solid #eee; vertical-align:top; }
    td:first-child { width:32%; color:#667085; } .signature { font-size:28px; font-style:italic; color:#9f1d24; padding:18px 0 6px; border-bottom:1px solid #222; }
    .hash { font-family: DejaVu Sans Mono, monospace; font-size:8px; word-break:break-all; background:#f8fafc; padding:9px; }
    .notice { margin-top:24px; padding:15px; background:#fff3f3; border-left:4px solid #b71920; line-height:1.6; }
    .footer { position:fixed; bottom:0; left:0; right:0; text-align:center; color:#98a2b3; font-size:9px; }
</style></head><body>
<div class="top">@if($logoDataUri)<img class="logo" src="{{ $logoDataUri }}">@endif</div>
<h1>Digitally Signed Contract Certificate</h1>
<p class="muted">Divertex Global Strategies Corporation · Generated {{ $signature->signed_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}</p>
<div class="panel"><table>
    <tr><td>Contract</td><td><strong>{{ $contract->title }}</strong></td></tr>
    <tr><td>Contract type</td><td>{{ str($contract->contract_type)->replace('_', ' ')->title() }}</td></tr>
    <tr><td>Reference</td><td>{{ $contract->reference_number ?: 'Contract #'.$contract->id }}</td></tr>
    <tr><td>Document version</td><td>Version {{ $contract->version }}</td></tr>
    <tr><td>Effective date</td><td>{{ $contract->effective_date->format('M d, Y') }}</td></tr>
    <tr><td>Original filename</td><td>{{ $contract->original_filename }}</td></tr>
</table></div>
<div class="panel"><h2>Signer identity</h2><table>
    <tr><td>Signer</td><td>{{ $signature->signer_name }}</td></tr>
    <tr><td>Employee ID</td><td>{{ $signature->signer_username ?: 'Not assigned' }}</td></tr>
    <tr><td>Email</td><td>{{ $signature->signer_email }}</td></tr>
    <tr><td>Role</td><td>{{ str($signature->signer_role)->replace('_', ' ')->title() }}</td></tr>
    <tr><td>Signed at</td><td>{{ $signature->signed_at->timezone('Asia/Manila')->format('M d, Y h:i:s A') }} Asia/Manila</td></tr>
    <tr><td>IP address</td><td>{{ $signature->ip_address ?: 'Unavailable' }}</td></tr>
</table><div class="signature">{{ $signature->signature_name }}</div><p class="muted">Electronic signature</p></div>
<div class="notice"><strong>Electronic consent</strong><br>{{ $signature->consent_text }}</div>
<h3>Integrity verification</h3><p class="muted">This certificate is bound to the exact issued source document. Any change to that file produces a different SHA-256 value.</p>
<p>Original document SHA-256</p><div class="hash">{{ $signature->document_sha256 }}</div>
<p>Signature record HMAC</p><div class="hash">{{ $signature->signature_hash }}</div>
<div class="footer">Signed contract certificate · Contract {{ $contract->id }} · Version {{ $contract->version }}</div>
</body></html>
