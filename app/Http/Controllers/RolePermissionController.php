<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Services\AuditLogService;
use App\Services\RolePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RolePermissionController extends Controller
{
    public function index(Request $request, RolePermissionService $permissions)
    {
        $this->authorizeAdmin($request);

        return Inertia::render('role-permissions', [
            'roles' => RolePermissionService::ROLES,
            'definitions' => $permissions->definitions(),
            'matrix' => $permissions->matrix(),
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function update(Request $request, string $role, RolePermissionService $permissions)
    {
        $this->authorizeAdmin($request);
        abort_unless(isset(RolePermissionService::ROLES[$role]), 404);
        $keys = array_keys($permissions->definitions());
        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['required', 'boolean'],
        ]);
        $submitted = array_intersect_key($data['permissions'], array_flip($keys));
        abort_unless(count($submitted) === count($keys), 422, 'Submit the complete permission matrix for this role.');
        $before = $permissions->matrix()[$role];

        DB::transaction(function () use ($request, $role, $submitted, $before): void {
            foreach ($submitted as $permission => $allowed) {
                RolePermission::updateOrCreate(
                    ['role' => $role, 'permission' => $permission],
                    ['allowed' => (bool) $allowed, 'updated_by' => $request->user()->id],
                );
            }
            $changes = collect($submitted)->filter(fn ($allowed, $permission) => (bool) $allowed !== (bool) $before[$permission])->map(fn ($allowed, $permission) => ['permission' => $permission, 'allowed' => (bool) $allowed])->values()->all();
            app(AuditLogService::class)->record($request->user(), 'permissions', 'Role permissions updated', RolePermission::class, metadata: ['role' => $role, 'changes' => $changes]);
        });

        return back()->with('status', RolePermissionService::ROLES[$role].' permissions updated.');
    }

    public function reset(Request $request, string $role)
    {
        $this->authorizeAdmin($request);
        abort_unless(isset(RolePermissionService::ROLES[$role]), 404);
        $count = RolePermission::where('role', $role)->delete();
        app(AuditLogService::class)->record($request->user(), 'permissions', 'Role permissions reset to defaults', RolePermission::class, metadata: ['role' => $role, 'overrides_removed' => $count]);

        return back()->with('status', RolePermissionService::ROLES[$role].' restored to default permissions.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin' && $request->user()->canAccessAccount(), 403);
    }
}
