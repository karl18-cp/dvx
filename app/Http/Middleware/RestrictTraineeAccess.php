<?php

namespace App\Http\Middleware;

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
            if ($request->is('logout')) {
                return $next($request);
            }
            abort_unless($user->status === 'active' && $user->training_status === 'in_training', 403, 'Your training access has ended. Please contact your administrator.');
            if ($request->is('dashboard')) {
                return redirect('/my-attendance');
            }
            abort_unless($request->is('my-attendance', 'my-attendance/*', 'assessments', 'assessments/*', 'my-coaching', 'my-coaching/*', 'settings', 'settings/*', 'user/*', 'notification-feed', 'notification-feed/*', 'users/*/photo'), 403);
        }

        return $next($request);
    }
}
