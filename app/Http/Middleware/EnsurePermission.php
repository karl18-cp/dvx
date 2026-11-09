<?php

namespace App\Http\Middleware;

use App\Services\RolePermissionService;
use Closure;
use Illuminate\Http\Request;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        abort_unless(app(RolePermissionService::class)->can($request->user(), $permission), 403);

        return $next($request);
    }
}
