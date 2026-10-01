<?php

namespace App\Http\Middleware;

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
        if ($request->is('dashboard')) {
            return redirect('/accounting');
        }
        $attendanceAccess = $request->isMethod('GET') && $request->is('attendance')
            || $request->isMethod('PUT') && $request->routeIs('attendance.override-time', 'attendance.override-hours');
        abort_unless($attendanceAccess || $request->is('/', 'logout', 'my-payslips', 'my-payslips/*', 'accounting', 'accounting/*', 'settings', 'settings/*', 'user/*', 'users/*/photo', 'notification-feed', 'notification-feed/*'), 403);

        return $next($request);
    }
}
