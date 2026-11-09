<?php

namespace App\Http\Middleware;

use App\Services\RolePermissionService;
use Closure;
use Illuminate\Http\Request;

class RestrictTraineeAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user?->role === 'trainee' || $user?->training_status === 'rejected') {
            $user = $user->fresh();
        }
        if ($user && ($user->role === 'trainee' || $user->training_status === 'rejected')) {
            if ($request->is('/', 'logout')) {
                return $next($request);
            }
            abort_unless($user->canAccessAccount(), 403, 'Your training access has ended. Please contact your administrator.');
            if (app(RolePermissionService::class)->allowsRestrictedPath($user, $request)) {
                return $next($request);
            }
            if ($request->is('dashboard')) {
                return redirect('/my-attendance');
            }
            abort_unless($request->is('daily-brief', 'daily-brief/*', 'my-training-plan', 'my-attendance', 'my-attendance/*', 'performance', 'performance/*', 'assessments', 'assessments/*', 'my-coaching', 'my-coaching/*', 'settings', 'settings/*', 'user/*', 'notification-feed', 'notification-feed/*', 'users/*/photo'), 403);
        }

        return $next($request);
    }
}
