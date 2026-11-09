<?php

namespace App\Http\Controllers;

use App\Models\EmployeeContract;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EmployeeContractController extends Controller
{
    private const TYPES = ['training', 'employment', 'quitclaim'];

    private const STATUSES = ['draft', 'active', 'expired', 'terminated', 'superseded'];

    public function index(Request $request, AccountingService $service): JsonResponse
    {
        $service->authorize($request->user());
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'status' => ['nullable', Rule::in([...self::STATUSES, 'archived'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = EmployeeContract::with(['employee:id,name,username,role,status', 'creator:id,name', 'signature:id,employee_contract_id,signer_name,signed_at,signature_hash'])
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($match) => $match
                ->where('title', 'like', '%'.$value.'%')
                ->orWhere('reference_number', 'like', '%'.$value.'%')
                ->orWhereHas('employee', fn ($employee) => $employee->where('name', 'like', '%'.$value.'%')->orWhere('username', 'like', '%'.$value.'%'))))
            ->when($filters['type'] ?? null, fn ($q, $value) => $q->where('contract_type', $value));
        if (($filters['status'] ?? null) === 'archived') {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at')->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value));
        }

        return response()->json([
            'records' => $query->latest('id')->paginate(20),
            'summary' => [
                'training' => EmployeeContract::whereNull('archived_at')->where('contract_type', 'training')->count(),
                'employment' => EmployeeContract::whereNull('archived_at')->where('contract_type', 'employment')->count(),
                'quitclaim' => EmployeeContract::whereNull('archived_at')->where('contract_type', 'quitclaim')->count(),
                'active' => EmployeeContract::whereNull('archived_at')->where('status', 'active')->count(),
                'archived' => EmployeeContract::whereNotNull('archived_at')->count(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, AccountingService $service): RedirectResponse
    {
        $service->authorizeFinance($request->user());
        $data = $request->validate($this->rules(true));
        $employee = User::whereKey($data['user_id'])->whereNotNull('username')->firstOrFail();
        $storedPath = null;

        try {
            DB::transaction(function () use ($request, $data, $employee, &$storedPath) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                if (EmployeeContract::where('request_key', $data['request_key'])->exists()) {
                    return;
                }
                $file = $request->file('document');
                $storedPath = $file->store('employee-contracts/'.$employee->id, 'local');
                if (! $storedPath) {
                    throw new \RuntimeException('The contract file could not be saved.');
                }
                $contract = EmployeeContract::create([
                    ...$this->attributes($data),
                    'version_group' => (string) Str::uuid(),
                    'version' => 1,
                    'document_sha256' => hash_file('sha256', Storage::disk('local')->path($storedPath)),
                    'signing_status' => 'draft',
                    'file_disk' => 'local',
                    'file_path' => $storedPath,
                    'original_filename' => $this->filename($file->getClientOriginalName()),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'file_size' => $file->getSize(),
                    'created_by' => $request->user()->id,
                ]);
                $this->audit($request, $contract, 'Employee contract stored');
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }

        return back()->with('status', 'Contract stored and linked to '.$employee->name.'.');
    }

    public function show(Request $request, EmployeeContract $contract, AccountingService $service): JsonResponse
    {
        $service->authorize($request->user());

        return response()->json($contract->load(['employee:id,name,username,role,status', 'creator:id,name', 'updater:id,name', 'signature']))
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, EmployeeContract $contract, AccountingService $service): RedirectResponse
    {
        $service->authorizeFinance($request->user());
        $data = $request->validate($this->rules(false));
        User::whereKey($data['user_id'])->whereNotNull('username')->firstOrFail();
        $newPath = null;
        $oldFile = null;

        try {
            $file = $request->file('document');
            if ($file) {
                $newPath = $file->store('employee-contracts/'.$data['user_id'], 'local');
                if (! $newPath) {
                    throw new \RuntimeException('The replacement contract file could not be saved.');
                }
            }
            DB::transaction(function () use ($request, $contract, $data, $file, $newPath, &$oldFile) {
                $contract = EmployeeContract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
                if ($contract->archived_at) {
                    throw ValidationException::withMessages(['contract' => 'Archived contracts are read-only.']);
                }
                if ($contract->signing_status !== 'draft') {
                    throw ValidationException::withMessages(['contract' => 'Issued or signed contract versions are immutable. Create a new version instead.']);
                }
                $attributes = [...$this->attributes($data), 'updated_by' => $request->user()->id];
                if ($file) {
                    $oldFile = [$contract->file_disk, $contract->file_path];
                    $attributes += [
                        'file_disk' => 'local',
                        'file_path' => $newPath,
                        'original_filename' => $this->filename($file->getClientOriginalName()),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'file_size' => $file->getSize(),
                        'document_sha256' => hash_file('sha256', Storage::disk('local')->path($newPath)),
                    ];
                }
                $contract->update($attributes);
                $this->audit($request, $contract, 'Employee contract updated');
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

        return back()->with('status', 'Contract details updated.');
    }

    public function archive(Request $request, EmployeeContract $contract, AccountingService $service): RedirectResponse
    {
        $service->authorizeFinance($request->user());
        $data = $request->validate(['archive_reason' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($request, $contract, $data) {
            $contract = EmployeeContract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
            if ($contract->archived_at) {
                throw ValidationException::withMessages(['archive_reason' => 'This contract is already archived.']);
            }
            if (in_array($contract->signing_status, ['issued', 'signed'], true)) {
                throw ValidationException::withMessages(['archive_reason' => 'Issued and signed contract versions must remain available.']);
            }
            $contract->update([...$data, 'archived_at' => now(), 'archived_by' => $request->user()->id]);
            $this->audit($request, $contract, 'Employee contract archived');
        });

        return back()->with('status', 'Contract archived. Its document remains available for record keeping.');
    }

    public function download(Request $request, EmployeeContract $contract, AccountingService $service): StreamedResponse
    {
        $service->authorize($request->user());
        abort_unless(Storage::disk($contract->file_disk)->exists($contract->file_path), 404);

        return Storage::disk($contract->file_disk)->download($contract->file_path, $contract->original_filename, [
            'Content-Type' => $contract->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(bool $creating): array
    {
        return [
            'request_key' => [$creating ? 'required' : 'nullable', 'uuid'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'contract_type' => ['required', Rule::in(self::TYPES)],
            'title' => ['required', 'string', 'max:180'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_date'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'notes' => ['nullable', 'string', 'max:3000'],
            'document' => [$creating ? 'required' : 'nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return collect($data)->only(['request_key', 'user_id', 'contract_type', 'title', 'reference_number', 'effective_date', 'end_date', 'status', 'notes'])->filter(fn ($value, $key) => $key !== 'request_key' || $value !== null)->all();
    }

    private function filename(string $name): string
    {
        return mb_substr(basename(str_replace('\\', '/', $name)), 0, 200);
    }

    private function audit(Request $request, EmployeeContract $contract, string $action): void
    {
        app(AuditLogService::class)->record($request->user(), 'contracts', $action, $contract, metadata: ['employee_id' => $contract->user_id, 'contract_type' => $contract->contract_type, 'status' => $contract->status], subjectId: $contract->user_id);
    }
}
