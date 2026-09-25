<?php

namespace App\Http\Controllers;

use App\Models\EmployeeSanction;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SanctionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'punishment' => ['nullable', Rule::in(EmployeeSanction::PUNISHMENTS)],
        ]);
        $search = trim($filters['search'] ?? '');
        $like = '%'.addcslashes($search, '%_\\').'%';

        return Inertia::render('sanctions', [
            'sanctions' => Sanction::query()->orderBy('name')->get(['id', 'name', 'description', 'created_at', 'updated_at']),
            'employees' => User::query()->orderBy('name')->get(['id', 'name', 'username', 'role', 'status']),
            'punishments' => EmployeeSanction::PUNISHMENTS,
            'records' => EmployeeSanction::query()->select(['id', 'sanction_name', 'sanction_description', 'employee_name', 'employee_username', 'employee_role', 'issuer_name', 'punishment', 'notes', 'created_at'])
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('employee_name', 'like', $like)->orWhere('employee_username', 'like', $like)->orWhere('sanction_name', 'like', $like)))
                ->when($filters['punishment'] ?? null, fn ($q, $value) => $q->where('punishment', $value))
                ->latest('id')->paginate(15)->withQueryString(),
            'filters' => ['search' => $search, 'punishment' => $filters['punishment'] ?? ''],
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->catalogData($request);
        DB::transaction(function () use ($request, $data): void {
            $sanction = Sanction::query()->create($data);
            $this->log($request, 'Sanction Created', Sanction::class, $sanction->id, $data);
        });

        return to_route('sanctions')->with('status', 'Sanction added to the catalog.');
    }

    public function update(Request $request, Sanction $sanction): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->catalogData($request, $sanction);
        DB::transaction(function () use ($request, $sanction, $data): void {
            $sanction->update($data);
            $this->log($request, 'Sanction Updated', Sanction::class, $sanction->id, $data);
        });

        return to_route('sanctions')->with('status', 'Sanction updated. Previously issued records keep their original details.');
    }

    public function destroy(Request $request, Sanction $sanction): RedirectResponse
    {
        $this->authorizeAdmin($request);
        DB::transaction(function () use ($request, $sanction): void {
            $sanction->delete();
            $this->log($request, 'Sanction Deleted', Sanction::class, $sanction->id, ['name' => $sanction->name]);
        });

        return to_route('sanctions')->with('status', 'Sanction removed from the catalog. Issued records are preserved.');
    }

    public function issue(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'sanction_id' => ['required', 'integer', 'exists:sanctions,id'],
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'punishment' => ['required', Rule::in(EmployeeSanction::PUNISHMENTS)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        DB::transaction(function () use ($request, $data): void {
            $sanction = Sanction::query()->lockForUpdate()->findOrFail($data['sanction_id']);
            $employee = User::query()->findOrFail($data['employee_id']);
            $record = EmployeeSanction::query()->firstOrCreate(['request_id' => $data['request_id']], [
                'sanction_id' => $sanction->id, 'employee_id' => $employee->id, 'issued_by' => $request->user()->id,
                'sanction_name' => $sanction->name, 'sanction_description' => $sanction->description,
                'employee_name' => $employee->name, 'employee_username' => $employee->username, 'employee_role' => $employee->role,
                'issuer_name' => $request->user()->name, 'punishment' => $data['punishment'], 'notes' => $data['notes'] ?? null,
            ]);
            if ($record->issued_by !== $request->user()->id || $record->sanction_id !== $sanction->id || $record->employee_id !== $employee->id || $record->punishment !== $data['punishment'] || $record->notes !== ($data['notes'] ?? null)) {
                throw ValidationException::withMessages(['request_id' => 'This submission has already been used. Refresh the page before issuing another sanction.']);
            }
            if ($record->wasRecentlyCreated) {
                $this->log($request, 'Sanction Issued', EmployeeSanction::class, $record->id, ['sanction_id' => $sanction->id, 'employee_id' => $employee->id, 'punishment' => $data['punishment']]);
            }
        }, attempts: 3);

        return to_route('sanctions')->with('status', 'Sanction issued and saved to employee history.');
    }

    private function catalogData(Request $request, ?Sanction $sanction = null): array
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('sanctions', 'name')->ignore($sanction)],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }

    private function log(Request $request, string $action, string $type, int $id, array $metadata): void
    {
        DB::table('assessment_activity_logs')->insert([
            'actor_id' => $request->user()->id, 'action' => $action, 'target_type' => $type, 'target_id' => $id,
            'metadata' => json_encode($metadata), 'created_at' => now(),
        ]);
    }
}
