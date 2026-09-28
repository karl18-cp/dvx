<?php

namespace App\Http\Controllers;

use App\Models\EmployeeBankAccount;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeBankAccountController extends Controller
{
    public function index(Request $request, AccountingService $service)
    {
        $service->authorize($request->user());
        $data = $request->validate(['search' => ['nullable', 'string', 'max:150'], 'party_type' => ['nullable', Rule::in(['payee', 'payor', 'both'])], 'page' => ['nullable', 'integer', 'min:1']]);
        $accounts = EmployeeBankAccount::with('employee:id,name,username')
            ->when($data['search'] ?? null, fn ($q, $v) => $q->whereHas('employee', fn ($u) => $u->where(fn ($names) => $names->where('name', 'like', '%'.$v.'%')->orWhere('username', 'like', '%'.$v.'%'))))
            ->when($data['party_type'] ?? null, fn ($q, $v) => $q->whereIn('party_type', $v === 'both' ? ['both'] : [$v, 'both']))
            ->latest('id')->paginate(20);
        $accounts->through(fn ($account) => [...$account->toArray(), 'masked_number' => '•••• '.substr($account->account_number, -4)]);

        return response()->json(['accounts' => $accounts, 'employees' => User::whereNotNull('username')->orderBy('name')->get(['id', 'name', 'username'])])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, EmployeeBankAccount $bankAccount, AccountingService $service)
    {
        $service->authorize($request->user());
        $this->audit($request, $bankAccount, 'Bank information viewed');

        return response()->json($bankAccount->makeVisible(['account_holder', 'account_number', 'notes']))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, AccountingService $service)
    {
        $service->authorize($request->user());
        $data = $this->validated($request);
        $request->validate(['request_key' => ['required', 'uuid']]);
        DB::transaction(function () use ($request, $data) {
            User::whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            if (EmployeeBankAccount::where('request_key', $request->input('request_key'))->exists()) {
                return;
            }
            $this->checkDuplicate($data);
            $account = EmployeeBankAccount::create([...$data, 'request_key' => $request->input('request_key'), 'created_by' => $request->user()->id]);
            $this->audit($request, $account, 'Bank information added');
        });

        return back();
    }

    public function update(Request $request, EmployeeBankAccount $bankAccount, AccountingService $service)
    {
        $service->authorize($request->user());
        $data = $this->validated($request);
        $request->validate(['version' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($request, $bankAccount, $data) {
            User::whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            $account = EmployeeBankAccount::whereKey($bankAccount->id)->lockForUpdate()->firstOrFail();
            $this->checkVersion($request, $account);
            $this->checkDuplicate($data, $account->id);
            $account->update([...$data, 'version' => $account->version + 1]);
            $this->audit($request, $account, 'Bank information updated');
        });

        return back();
    }

    public function destroy(Request $request, EmployeeBankAccount $bankAccount, AccountingService $service)
    {
        $service->authorize($request->user());
        $request->validate(['version' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($request, $bankAccount) {
            $account = EmployeeBankAccount::whereKey($bankAccount->id)->lockForUpdate()->firstOrFail();
            $this->checkVersion($request, $account);
            $this->audit($request, $account, 'Bank information deleted');
            $account->delete();
        });

        return back();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'], 'party_type' => ['required', Rule::in(['payee', 'payor', 'both'])],
            'bank_name' => ['required', 'string', 'max:150'], 'branch' => ['nullable', 'string', 'max:150'],
            'account_holder' => ['required', 'string', 'max:200'], 'account_number' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9 -]{3,49}$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $number = strtoupper(preg_replace('/[ -]/', '', $data['account_number']));
        if (strlen($number) < 4) {
            throw ValidationException::withMessages(['account_number' => 'Enter at least four letters or digits.']);
        }
        $data['fingerprint'] = hash_hmac('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', trim($data['bank_name']))).'|'.$number, config('app.key'));

        return $data;
    }

    private function checkDuplicate(array $data, ?int $id = null): void
    {
        if (EmployeeBankAccount::where('user_id', $data['user_id'])->where('fingerprint', $data['fingerprint'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
            throw ValidationException::withMessages(['account_number' => 'This employee already has this bank account. Edit the existing record instead.']);
        }
    }

    private function checkVersion(Request $request, EmployeeBankAccount $account): void
    {
        if ((int) $request->input('version') !== $account->version) {
            throw ValidationException::withMessages(['version' => 'This record was changed by someone else. Close this window and refresh before trying again.']);
        }
    }

    private function audit(Request $request, EmployeeBankAccount $account, string $action): void
    {
        DB::table('assessment_activity_logs')->insert(['actor_id' => $request->user()->id, 'action' => $action, 'target_type' => EmployeeBankAccount::class, 'target_id' => $account->id, 'metadata' => json_encode(['employee_id' => $account->user_id]), 'created_at' => now()]);
    }
}
