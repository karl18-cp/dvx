<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountStatusService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountStatusController extends Controller
{
    public function index(Request $request, AccountStatusService $service)
    {
        return Inertia::render('account-statuses', [
            'employees' => $service->visible($request->user())->with('teamMembership.team:id,name')->orderBy('name')->get()->map(fn ($u) => [
                'id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'avatar' => $u->avatar, 'role' => $u->role,
                'status' => $u->role === 'trainee' ? $u->training_status : $u->status, 'team' => $u->teamMembership?->team?->name,
                'can_edit' => $u->id !== $request->user()->id, 'access' => $u->canAccessAccount(),
            ]),
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function update(Request $request, User $employee, AccountStatusService $service)
    {
        $data = $request->validate(['status' => ['required', 'string'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $service->update($request->user(), $employee, $data['status'], $data['notes'] ?? null);

        return back()->with('status', 'Account status updated. Access rules take effect immediately.');
    }
}
