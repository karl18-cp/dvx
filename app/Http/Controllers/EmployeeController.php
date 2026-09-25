<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\User;
use App\Services\EmployeeRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'manager'], true), 403);
        $employees = User::query()
            ->with(['personalInformation', 'campaignSchedule:id,name'])
            ->whereNotNull('username')
            ->orderBy('username')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'employeeId' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'position' => $this->positionLabel($user->role),
                'status' => $user->status,
                'team' => $user->team,
                'schedule' => $user->campaignSchedule?->name ?? $user->schedule,
                'personalInformation' => $user->personalInformation ? [
                    'email' => $user->personalInformation->email,
                    'birthDate' => $user->personalInformation->birth_date->toDateString(),
                    'startDate' => $user->personalInformation->start_date->toDateString(),
                    'gender' => $user->personalInformation->gender,
                    'civilStatus' => $user->personalInformation->civil_status,
                    'phone' => $user->personalInformation->phone,
                    'address' => $user->personalInformation->address,
                    'emergencyName' => $user->personalInformation->emergency_contact_name,
                    'emergencyRelationship' => $user->personalInformation->emergency_contact_relationship,
                    'emergencyPhone' => $user->personalInformation->emergency_contact_phone,
                    'emergencyAddress' => $user->personalInformation->emergency_contact_address,
                ] : null,
            ]);

        return Inertia::render('employees', [
            'nextEmployeeId' => $this->nextEmployeeId(),
            'canManageEmployees' => $request->user()?->role === 'admin',
            'statusMessage' => $request->session()->get('status'),
            'employees' => $employees,
            'stats' => [
                'total' => $employees->count(),
                'active' => $employees->where('status', 'active')->count(),
                'onLeave' => $employees->where('status', 'floating')->count(),
                'inactive' => $employees
                    ->whereIn('status', ['suspended', 'resigned', 'terminated'])
                    ->count(),
            ],
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $employeeId = $this->nextEmployeeId(lockForUpdate: true);

            $role = $this->roleValue($data['position']);

            $user = User::query()->create([
                'username' => $employeeId,
                'name' => $data['full_name'],
                'email' => $data['email'],
                'role' => $role,
                'password' => $employeeId,
            ]);

            $user->personalInformation()->create([
                'email' => $data['email'],
                'birth_date' => $data['birth_date'],
                'start_date' => $data['start_date'],
                'gender' => $data['gender'],
                'civil_status' => $data['civil_status'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'emergency_contact_name' => $data['emergency_contact_name'],
                'emergency_contact_relationship' => $data['emergency_contact_relationship'],
                'emergency_contact_phone' => $data['emergency_contact_phone'],
                'emergency_contact_address' => $data['emergency_contact_address'] ?? null,
            ]);

            $user->faceCredential()->create([
                'encrypted_descriptor' => array_map(
                    static fn (mixed $value): float => (float) $value,
                    $data['face_descriptor'],
                ),
                'model_version' => $data['face_model_version'],
                'consented_at' => now(),
                'enrolled_at' => now(),
            ]);
        }, attempts: 3);

        return to_route('employees')->with('status', 'Employee account created successfully.');
    }

    public function update(UpdateEmployeeRequest $request, User $employee, EmployeeRoleService $roles): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $employee, $request, $roles): void {
            $roles->change($request->user(), $employee, $this->roleValue($data['position']));
            $employee->update([
                'name' => $data['full_name'],
                'email' => $data['email'],
                'status' => $data['status'],
                ...(! empty($data['new_password']) ? ['password' => $data['new_password']] : []),
            ]);

            $employee->personalInformation()->updateOrCreate(
                ['user_id' => $employee->id],
                [
                    'email' => $data['email'],
                    'birth_date' => $data['birth_date'],
                    'start_date' => $data['start_date'],
                    'gender' => $data['gender'],
                    'civil_status' => $data['civil_status'],
                    'phone' => $data['phone'],
                    'address' => $data['address'],
                    'emergency_contact_name' => $data['emergency_contact_name'],
                    'emergency_contact_relationship' => $data['emergency_contact_relationship'],
                    'emergency_contact_phone' => $data['emergency_contact_phone'],
                    'emergency_contact_address' => $data['emergency_contact_address'] ?? null,
                ],
            );
        });

        return to_route('employees')->with('status', 'Employee information updated successfully.');
    }

    public function updateRole(Request $request, User $employee, EmployeeRoleService $roles): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $data = $request->validate(['position' => ['required', Rule::in(array_keys(EmployeeRoleService::ROLES))]]);
        $roles->change($request->user(), $employee, $this->roleValue($data['position']));

        return to_route('employees')->with('status', $employee->name.' is now '.$data['position'].'.');
    }

    private function nextEmployeeId(bool $lockForUpdate = false): string
    {
        $query = User::query()->where('username', 'like', 'DVX%');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $highestEmployeeNumber = $query
            ->pluck('username')
            ->map(function (?string $username): int {
                return preg_match('/^DVX(\d+)$/i', (string) $username, $matches)
                    ? (int) $matches[1]
                    : 0;
            })
            ->max() ?? 0;

        return sprintf('DVX%03d', max(2, $highestEmployeeNumber + 1));
    }

    private function positionLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'Admin',
            'team_leader' => 'Team Leader',
            'agent' => 'Agent',
            'it_admin' => 'IT Admin',
            'it_support' => 'IT Support',
            'it_developer' => 'IT Developer',
            default => ucwords(str_replace('_', ' ', $role)),
        };
    }

    private function roleValue(string $position): string
    {
        return EmployeeRoleService::ROLES[$position];
    }
}
