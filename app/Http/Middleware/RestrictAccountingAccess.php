<?php

namespace App\Http\Middleware;

use App\Services\RolePermissionService;
use Closure;
use Illuminate\Http\Request;

class RestrictAccountingAccess
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->role !== 'accounting') {
            return $next($request);
        }
        abort_unless($request->user()->canAccessAccount(), 403);
        if (app(RolePermissionService::class)->allowsRestrictedPath($request->user(), $request)) {
            return $next($request);
        }
        if ($request->is('dashboard')) {
            return redirect('/accounting/dashboard');
        }
        $attendanceAccess = $request->isMethod('GET') && $request->is('attendance')
            || $request->isMethod('PUT') && $request->routeIs('attendance.override-time', 'attendance.override-hours');
        abort_unless($attendanceAccess || $request->is('/', 'logout', 'daily-brief', 'daily-brief/*', 'my-payslips', 'my-payslips/*', 'performance', 'performance/*', 'accounting', 'accounting/*', 'leave-credits', 'settings', 'settings/*', 'user/*', 'users/*/photo', 'notification-feed', 'notification-feed/*'), 403);

        return $next($request);
    }
}
