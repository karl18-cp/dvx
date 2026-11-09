<?php

namespace App\Http\Controllers;

use App\Mail\EmployeeDocumentReady;
use App\Models\EmployeeDocumentRequest;
use App\Models\User;
use App\Services\AssessmentNotificationService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EmployeeDocumentRequestController extends Controller
{
    private const TYPES = ['certificate_of_employment', 'payslip_copy', 'employment_verification', 'other'];

    private const STATUSES = ['submitted', 'in_review', 'ready', 'released', 'rejected', 'cancelled'];

    public function index(Request $request)
    {
        $actor = $this->actor($request);
        $canManage = $actor->hasPermission('documents.manage');
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'], 'type' => ['nullable', Rule::in(self::TYPES)],
            'status' => ['nullable', Rule::in(self::STATUSES)], 'scope' => ['nullable', Rule::in(['mine', 'all'])],
        ]);
        $scope = $canManage ? ($filters['scope'] ?? 'all') : 'mine';
        $query = EmployeeDocumentRequest::query()->with(['employee:id,name,username,role,email', 'handler:id,name'])
            ->when($scope === 'mine', fn ($query) => $query->where('user_id', $actor->id))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('document_type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(fn ($match) => $match->where('purpose', 'like', '%'.$search.'%')->orWhere('other_document_type', 'like', '%'.$search.'%')
                    ->orWhereHas('employee', fn ($employee) => $employee->where('name', 'like', '%'.$search.'%')->orWhere('username', 'like', '%'.$search.'%')));
            });

        return Inertia::render('employee-document-requests', [
            'canManage' => $canManage, 'scope' => $scope, 'currentUserId' => $actor->id,
            'filters' => ['search' => $filters['search'] ?? '', 'type' => $filters['type'] ?? '', 'status' => $filters['status'] ?? ''],
            'requests' => (clone $query)->latest('id')->paginate(20)->withQueryString()->through(fn (EmployeeDocumentRequest $item) => $this->row($item)),
            'summary' => [
                'submitted' => (clone $query)->where('status', 'submitted')->count(),
                'in_review' => (clone $query)->where('status', 'in_review')->count(),
                'ready' => (clone $query)->where('status', 'ready')->count(),
                'released' => (clone $query)->where('status', 'released')->count(),
            ],
        ]);
    }

    public function store(Request $request, AuditLogService $audit, AssessmentNotificationService $notifications)
    {
        $actor = $this->actor($request);
        abort_if($actor->role === 'trainee', 403);
        $data = $request->validate([
            'request_key' => ['required', 'uuid'], 'document_type' => ['required', Rule::in(self::TYPES)],
            'other_document_type' => ['nullable', 'required_if:document_type,other', 'string', 'max:160'],
            'purpose' => ['required', 'string', 'min:5', 'max:2000'],
            'period_start' => ['nullable', 'required_if:document_type,payslip_copy', 'date'],
            'period_end' => ['nullable', 'required_if:document_type,payslip_copy', 'date', 'after_or_equal:period_start'],
            'delivery_method' => ['required', Rule::in(['portal', 'email', 'both'])],
            'employee_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $document = DB::transaction(fn () => EmployeeDocumentRequest::query()->firstOrCreate(
            ['request_key' => $data['request_key']],
            [...$data, 'user_id' => $actor->id, 'status' => 'submitted', 'submitted_at' => now()],
        ));
        $audit->record($actor, 'documents', 'Employee document requested', $document, metadata: ['document_type' => $document->document_type, 'delivery_method' => $document->delivery_method], subjectId: $actor->id);
        User::query()->where('status', 'active')->whereIn('role', ['admin', 'manager', 'accounting'])->get()->filter(fn (User $user) => $user->hasPermission('documents.manage'))->each(fn (User $user) => $notifications->createForUser($user, "document-request:{$document->id}:submitted:{$user->id}", 'document_request', 'New employee document request', "{$actor->name} requested ".str($document->document_type)->replace('_', ' ').'.', '/document-requests?scope=all'));

        return back()->with('success', 'Your document request was submitted.');
    }

    public function process(Request $request, EmployeeDocumentRequest $documentRequest, AuditLogService $audit, AssessmentNotificationService $notifications)
    {
        $actor = $this->actor($request);
        abort_unless($actor->hasPermission('documents.manage'), 403);
        if (in_array($documentRequest->status, ['released', 'cancelled'], true)) {
            throw ValidationException::withMessages(['status' => 'Released or cancelled requests are read-only.']);
        }
        $data = $request->validate([
            'status' => ['required', Rule::in(['in_review', 'ready', 'released', 'rejected'])],
            'reviewer_notes' => ['nullable', 'required_if:status,rejected', 'string', 'max:3000'],
            'document' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ]);
        $newPath = null;
        $oldFile = null;

        try {
            $file = $request->file('document');
            if ($file) {
                $newPath = $file->store('employee-document-requests/'.$documentRequest->user_id, 'local');
                throw_if(! $newPath, new \RuntimeException('The completed document could not be stored.'));
            }
            DB::transaction(function () use ($actor, $audit, $documentRequest, $data, $file, $newPath, &$oldFile) {
                $document = EmployeeDocumentRequest::query()->lockForUpdate()->findOrFail($documentRequest->id);
                if (in_array($data['status'], ['ready', 'released'], true) && ! $file && ! $document->file_path) {
                    throw ValidationException::withMessages(['document' => 'Upload the completed document before marking this request ready or released.']);
                }
                $attributes = [
                    'status' => $data['status'], 'reviewer_notes' => $data['reviewer_notes'] ?? null, 'handled_by' => $actor->id,
                    'completed_at' => in_array($data['status'], ['ready', 'released'], true) ? ($document->completed_at ?? now()) : null,
                    'released_at' => $data['status'] === 'released' ? now() : null, 'email_error' => null,
                ];
                if ($file) {
                    $oldFile = $document->file_path ? [$document->file_disk, $document->file_path] : null;
                    $attributes += ['file_disk' => 'local', 'file_path' => $newPath, 'original_filename' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 200), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'file_size' => $file->getSize()];
                }
                $before = $document->only(['status', 'reviewer_notes', 'original_filename']);
                $document->update($attributes);
                $audit->record($actor, 'documents', 'Employee document request updated', $document, metadata: ['before' => $before, 'after' => ['status' => $document->status, 'reviewer_notes' => $document->reviewer_notes, 'original_filename' => $document->original_filename]], subjectId: $document->user_id);
            });
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }
        if ($oldFile) {
            Storage::disk($oldFile[0])->delete($oldFile[1]);
        }

        $documentRequest->refresh()->load('employee');
        $notifications->createForUser($documentRequest->employee, "document-request:{$documentRequest->id}:{$documentRequest->status}", 'document_request', 'Document request '.str($documentRequest->status)->replace('_', ' '), $documentRequest->reviewer_notes ?: 'Your HR document request status was updated.', '/document-requests?scope=mine');
        if ($documentRequest->status === 'released' && in_array($documentRequest->delivery_method, ['email', 'both'], true)) {
            try {
                Mail::to($documentRequest->employee->email)->send(new EmployeeDocumentReady($documentRequest));
                $documentRequest->update(['email_sent_at' => now(), 'email_error' => null]);
            } catch (Throwable $exception) {
                $documentRequest->update(['email_error' => class_basename($exception)]);
            }
        }

        return back()->with('success', 'Document request updated.');
    }

    public function cancel(Request $request, EmployeeDocumentRequest $documentRequest, AuditLogService $audit)
    {
        $actor = $this->actor($request);
        abort_unless($documentRequest->user_id === $actor->id, 403);
        if ($documentRequest->status !== 'submitted') {
            throw ValidationException::withMessages(['status' => 'Only newly submitted requests can be cancelled.']);
        }
        $documentRequest->update(['status' => 'cancelled']);
        $audit->record($actor, 'documents', 'Employee document request cancelled', $documentRequest, subjectId: $actor->id);

        return back()->with('success', 'Document request cancelled.');
    }

    public function download(Request $request, EmployeeDocumentRequest $documentRequest): StreamedResponse
    {
        $actor = $this->actor($request);
        abort_unless($documentRequest->user_id === $actor->id || $actor->hasPermission('documents.manage'), 403);
        abort_unless(in_array($documentRequest->status, ['ready', 'released'], true) && $documentRequest->file_path && Storage::disk($documentRequest->file_disk)->exists($documentRequest->file_path), 404);

        return Storage::disk($documentRequest->file_disk)->download($documentRequest->file_path, $documentRequest->original_filename, ['Content-Type' => $documentRequest->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function email(Request $request, EmployeeDocumentRequest $documentRequest)
    {
        $actor = $this->actor($request);
        abort_unless($actor->hasPermission('documents.manage'), 403);
        abort_unless($documentRequest->status === 'released' && in_array($documentRequest->delivery_method, ['email', 'both'], true) && $documentRequest->file_path, 422);
        $documentRequest->load('employee');
        try {
            Mail::to($documentRequest->employee->email)->send(new EmployeeDocumentReady($documentRequest));
            $documentRequest->update(['email_sent_at' => now(), 'email_error' => null]);
        } catch (Throwable $exception) {
            $documentRequest->update(['email_error' => class_basename($exception)]);

            throw ValidationException::withMessages(['email' => 'The email could not be delivered. The request remains available for retry.']);
        }

        return back()->with('success', 'The completed document was emailed again.');
    }

    private function row(EmployeeDocumentRequest $item): array
    {
        return [
            ...$item->only(['id', 'user_id', 'document_type', 'other_document_type', 'purpose', 'period_start', 'period_end', 'delivery_method', 'status', 'employee_notes', 'reviewer_notes', 'original_filename', 'mime_type', 'file_size', 'email_error']),
            'employee' => $item->employee?->only(['id', 'name', 'username', 'role', 'email']), 'handler' => $item->handler?->name,
            'submitted_at' => $item->submitted_at?->toIso8601String(), 'completed_at' => $item->completed_at?->toIso8601String(),
            'released_at' => $item->released_at?->toIso8601String(), 'email_sent_at' => $item->email_sent_at?->toIso8601String(),
            'download_available' => in_array($item->status, ['ready', 'released'], true) && (bool) $item->file_path,
        ];
    }

    private function actor(Request $request): User
    {
        $actor = $request->user()->fresh();
        abort_unless($actor->canAccessAccount(), 403);

        return $actor;
    }
}
