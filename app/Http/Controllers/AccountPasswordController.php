<?php

namespace App\Http\Controllers;

use App\Services\AccountIdentity;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Actions\CompletePasswordReset;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse;
use Laravel\Fortify\Contracts\RequestPasswordResetLinkViewResponse;
use Laravel\Fortify\Contracts\ResetPasswordViewResponse;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

class AccountPasswordController extends Controller
{
    public function create(Request $request)
    {
        return $request->routeIs('password.reset')
            ? app(ResetPasswordViewResponse::class)
            : app(RequestPasswordResetLinkViewResponse::class);
    }

    public function store(Request $request, AccountIdentity $identity)
    {
        $request->validate(['email' => ['required', 'string', 'max:255']]);
        $user = $identity->resolve($request->string('email')->toString());
        $broker = Password::broker(config('fortify.passwords'));
        if ($request->routeIs('password.email')) {
            $status = $user ? $broker->sendResetLink(['id' => $user->id]) : Password::INVALID_USER;

            return $status === Password::RESET_LINK_SENT
                ? app(SuccessfulPasswordResetLinkRequestResponse::class, ['status' => $status])
                : app(FailedPasswordResetLinkRequestResponse::class, ['status' => $status]);
        }
        $request->validate(['token' => ['required', 'string'], 'password' => ['required', 'string']]);
        $status = $user ? $broker->reset(['id' => $user->id, ...$request->only('password', 'password_confirmation', 'token')], function ($user) use ($request): void {
            app(ResetsUserPasswords::class)->reset($user, $request->all());
            app(CompletePasswordReset::class)(app(StatefulGuard::class), $user);
        }) : Password::INVALID_USER;

        return $status === Password::PASSWORD_RESET
            ? app(PasswordResetResponse::class, ['status' => $status])
            : app(FailedPasswordResetResponse::class, ['status' => $status]);
    }
}
