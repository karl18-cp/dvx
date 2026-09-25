<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RestrictQaAdminAccess
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->role !== 'qa_admin') {
            return $next($request);
        }
        if ($request->is('/', 'logout')) {
            return $next($request);
        }
        abort_unless($request->user()->status === 'active', 403);
        if ($request->is('dashboard')) {
            return redirect('/management/qa-dashboard');
        }

        $modules = [
            'assessments', 'question-bank', 'assessment-media', 'assessment-assignments',
            'assessment-assignment-schedule', 'assessment-employees', 'assessment-recipient-count',
            'assessment-reviews', 'assessment-results', 'assessment-results-export', 'assessment-teams',
            'training-library', 'campaign-analytics', 'coaching', 'qa-dashboard', 'call-evaluations', 'qa-scorecards',
        ];
        $allowed = ['settings', 'settings/*', 'user/*', 'users/*/photo', 'notification-feed', 'notification-feed/*', 'management/employees/*/training-profile'];
        foreach ($modules as $module) {
            $allowed[] = 'management/'.$module;
            $allowed[] = 'management/'.$module.'/*';
        }
        abort_unless($request->is(...$allowed), 403);

        return $next($request);
    }
}
