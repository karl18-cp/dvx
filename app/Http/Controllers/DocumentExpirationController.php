<?php

namespace App\Http\Controllers;

use App\Models\ComplianceDocument;
use App\Models\EmployeeContract;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DocumentExpirationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentExpirationController extends Controller
{
    private const TYPES = ['government_id', 'certification', 'medical_document', 'training_requirement', 'pre_employment_requirement', 'other'];

    public function index(Request $request)
    {
        $actor = $this->manager($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', Rule::in([...self::TYPES, 'contract'])],
            'state' => ['nullable', Rule::in(['current', 'due_soon', 'expired', 'waived'])],
            'subject_type' => ['nullable', Rule::in(['employee', 'applicant'])],
        ]);
        $today = CarbonImmutable::today('Asia/Manila');
        $documents = ComplianceDocument::query()
            ->with(['employee:id,name,username,role', 'applicant:id,first_name,last_name,email,position', 'creator:id,name'])
            ->latest('expires_at')->limit(500)->get()->map(fn (ComplianceDocument $document) => $this->documentRow($document, $today));
        $contracts = EmployeeContract::query()->with('employee:id,name,username,role')
            ->whereNull('archived_at')->whereNotNull('end_date')
            ->where(function ($query) {
                $query->where('status', 'active')->orWhereIn('signing_status', ['issued', 'signed']);
            })->where('signing_status', '!=', 'superseded')
            ->orderByDesc('version')->limit(500)->get()->unique('version_group')
            ->map(fn (EmployeeContract $contract) => $this->contractRow($contract, $today));
        $records = $documents->concat($contracts)->filter(function (array $row) use ($filters) {
            $search = mb_strtolower($filters['search'] ?? '');
            if ($search && ! str_contains(mb_strtolower(implode(' ', [$row['title'], $row['subject_name'], $row['document_number'] ?? '', $row['issuer'] ?? ''])), $search)) {
                return false;
            }

            return (! ($filters['type'] ?? null) || $row['document_type'] === $filters['type'])
                && (! ($filters['state'] ?? null) || $row['expiry_state'] === $filters['state'])
                && (! ($filters['subject_type'] ?? null) || $row['subject_type'] === $filters['subject_type']);
        })->sortBy(fn (array $row) => [$row['expiry_state'] === 'expired' ? 0 : 1, $row['expires_at']])->values();

        return Inertia::render('document-expirations', [
            'records' => $records,
            'employees' => User::query()->whereIn('role', ['admin', 'manager', 'qa_admin', 'accounting', 'team_leader', 'agent', 'trainee'])->orderBy('name')->get(['id', 'name', 'username', 'role', 'status']),
            'applicants' => JobApplication::query()->where(fn ($query) => $query->whereNull('applicant_stage')->orWhere('applicant_stage', '!=', 'failed'))->latest()->limit(500)->get(['id', 'first_name', 'last_name', 'email', 'position', 'applicant_stage']),
            'filters' => ['search' => $filters['search'] ?? '', 'type' => $filters['type'] ?? '', 'state' => $filters['state'] ?? '', 'subject_type' => $filters['subject_type'] ?? ''],
            'summary' => [
                'total' => $records->count(),
                'expired' => $records->where('expiry_state', 'expired')->count(),
                'due_soon' => $records->where('expiry_state', 'due_soon')->count(),
                'applicant_requirements' => $records->where('subject_type', 'applicant')->count(),
            ],
            'documentTypes' => self::TYPES,
            'today' => $today->toDateString(),
            'actor' => ['id' => $actor->id],
        ]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $actor = $this->manager($request);
        $data = $this->validated($request);
        $path = null;
        try {
            if ($request->hasFile('document')) {
                $path = $request->file('document')->store('compliance-documents', 'local');
            }
            $document = ComplianceDocument::create([...$this->attributes($data), ...$this->fileAttributes($request, $path), 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            $audit->record($actor, 'documents', 'Compliance document added', $document, metadata: ['document_type' => $document->document_type, 'expires_at' => $document->expires_at->toDateString(), 'subject_type' => $document->subject_type], subjectId: $document->user_id);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return back()->with('success', 'Document expiration record added.');
    }

    public function update(Request $request, ComplianceDocument $document, AuditLogService $audit)
    {
        $actor = $this->manager($request);
        $data = $this->validated($request);
        $oldPath = $document->file_path;
        $newPath = null;
        try {
            if ($request->hasFile('document')) {
                $newPath = $request->file('document')->store('compliance-documents', 'local');
            }
            $before = $document->only(['subject_type', 'user_id', 'job_application_id', 'document_type', 'title', 'expires_at', 'status']);
            $document->update([...$this->attributes($data), ...($newPath ? $this->fileAttributes($request, $newPath) : []), 'updated_by' => $actor->id]);
            if ($newPath && $oldPath) {
                Storage::disk($document->file_disk ?: 'local')->delete($oldPath);
            }
            $audit->record($actor, 'documents', 'Compliance document updated', $document, metadata: ['before' => $before, 'expires_at' => $document->expires_at->toDateString(), 'status' => $document->status], subjectId: $document->user_id);
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }

        return back()->with('success', 'Document expiration record updated.');
    }

    public function scan(Request $request, DocumentExpirationService $service)
    {
        $this->manager($request);
        $result = $service->scan();

        return back()->with('success', "Expiration scan complete. {$result['notifications_created']} new administrator alert(s) created.");
    }

    public function download(Request $request, ComplianceDocument $document): StreamedResponse
    {
        $this->manager($request);
        abort_unless($document->file_path && Storage::disk($document->file_disk)->exists($document->file_path), 404);

        return Storage::disk($document->file_disk)->download($document->file_path, $document->original_filename, ['Content-Type' => $document->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function contractDownload(Request $request, EmployeeContract $contract): StreamedResponse
    {
        $this->manager($request);
        abort_unless($contract->file_path && Storage::disk($contract->file_disk)->exists($contract->file_path), 404);

        return Storage::disk($contract->file_disk)->download($contract->file_path, $contract->original_filename, ['Content-Type' => $contract->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'subject_type' => ['required', Rule::in(['employee', 'applicant'])],
            'user_id' => ['nullable', 'required_if:subject_type,employee', 'exists:users,id'],
            'job_application_id' => ['nullable', 'required_if:subject_type,applicant', 'exists:job_applications,id'],
            'document_type' => ['required', Rule::in(self::TYPES)],
            'title' => ['required', 'string', 'max:255'], 'issuer' => ['nullable', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:120'], 'issued_at' => ['nullable', 'date'],
            'expires_at' => ['required', 'date', 'after_or_equal:issued_at'], 'remind_days_before' => ['required', 'integer', 'min:1', 'max:365'],
            'status' => ['required', Rule::in(['active', 'waived'])], 'notes' => ['nullable', 'string', 'max:3000'],
            'document' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'],
        ]);
    }

    private function attributes(array $data): array
    {
        return [
            ...collect($data)->except(['document', 'user_id', 'job_application_id'])->all(),
            'user_id' => $data['subject_type'] === 'employee' ? $data['user_id'] : null,
            'job_application_id' => $data['subject_type'] === 'applicant' ? $data['job_application_id'] : null,
        ];
    }

    private function fileAttributes(Request $request, ?string $path): array
    {
        if (! $path) {
            return [];
        }
        $file = $request->file('document');

        return ['file_disk' => 'local', 'file_path' => $path, 'original_filename' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'file_size' => $file->getSize()];
    }

    private function documentRow(ComplianceDocument $document, CarbonImmutable $today): array
    {
        $days = (int) $today->diffInDays($document->expires_at, false);
        $subject = $document->employee?->name ?? trim(($document->applicant?->first_name ?? '').' '.($document->applicant?->last_name ?? ''));

        return [...$document->only(['id', 'subject_type', 'user_id', 'job_application_id', 'document_type', 'title', 'issuer', 'document_number', 'issued_at', 'expires_at', 'remind_days_before', 'status', 'notes', 'original_filename', 'mime_type', 'file_size']), 'source_type' => 'document', 'subject_name' => $subject, 'subject_detail' => $document->employee?->username ?? $document->applicant?->email, 'days_remaining' => $days, 'expiry_state' => $this->state($document->status, $days, $document->remind_days_before), 'download_available' => (bool) $document->file_path];
    }

    private function contractRow(EmployeeContract $contract, CarbonImmutable $today): array
    {
        $days = (int) $today->diffInDays($contract->end_date, false);

        return ['id' => $contract->id, 'source_type' => 'contract', 'subject_type' => 'employee', 'user_id' => $contract->user_id, 'job_application_id' => null, 'document_type' => 'contract', 'title' => $contract->title, 'issuer' => 'Divertex Global Strategies Corporation', 'document_number' => $contract->reference_number, 'issued_at' => $contract->effective_date, 'expires_at' => $contract->end_date, 'remind_days_before' => 30, 'status' => $contract->status, 'notes' => null, 'original_filename' => $contract->original_filename, 'mime_type' => $contract->mime_type, 'file_size' => $contract->file_size, 'subject_name' => $contract->employee?->name, 'subject_detail' => $contract->employee?->username, 'days_remaining' => $days, 'expiry_state' => $this->state('active', $days, 30), 'download_available' => true];
    }

    private function state(string $status, int $days, int $remindDays): string
    {
        if ($status === 'waived') {
            return 'waived';
        }

        return $days < 0 ? 'expired' : ($days <= $remindDays ? 'due_soon' : 'current');
    }

    private function manager(Request $request): User
    {
        $actor = $request->user()->fresh();
        abort_unless($actor?->hasPermission('compliance_documents.manage'), 403);

        return $actor;
    }
}
