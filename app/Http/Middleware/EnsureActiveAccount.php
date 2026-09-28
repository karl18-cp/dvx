<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $user = $request->user()->fresh();
            if (! $user || ! $user->canAccessAccount()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Your account is not active. Contact your administrator.'], 403);
                }

                return redirect('/login')->withErrors(['email' => 'Your account is not active. Contact your administrator.']);
            }
            Auth::guard('web')->setUser($user);
        }

        return $next($request);
    }
}
