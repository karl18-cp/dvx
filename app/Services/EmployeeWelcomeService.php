<?php

namespace App\Services;

use App\Mail\EmployeeWelcome;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmployeeWelcomeService
{
    public function sendGraduation(User $employee): bool
    {
        if (config('mail.default') !== 'smtp' || ! filled(config('mail.mailers.smtp.username')) || ! filled(config('mail.mailers.smtp.password'))) {
            return false;
        }
        try {
            Mail::to($employee->email)->send((new \Illuminate\Mail\Mailable)
                ->subject('Divertex — your official employee ID')
                ->html('<h2>Welcome to the team</h2><p>Your training has been approved. Your official employee ID is <strong>'.e($employee->username).'</strong>.</p><p>Use this ID or your email address to sign in. If you still used your temporary password, it now matches this new employee ID. If you already changed your password, continue using that password.</p><p><a href="'.e(rtrim(config('app.url'), '/').'/login').'">Sign in to Divertex</a></p>'));

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Graduation email failed', ['employee_id' => $employee->id, 'exception_type' => $exception::class]);

            return false;
        }
    }

    public function send(User $employee, #[\SensitiveParameter] string $temporaryPassword): bool
    {
        // Never write account credentials to a log/array mail transport.
        if (config('mail.default') !== 'smtp' || ! filled(config('mail.mailers.smtp.username')) || ! filled(config('mail.mailers.smtp.password'))) {
            return false;
        }
        try {
            Mail::to($employee->email)->send(new EmployeeWelcome(
                $employee->name,
                $employee->username,
                $temporaryPassword,
                rtrim(config('app.url'), '/').'/login',
            ));

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Employee welcome email failed', ['employee_id' => $employee->id, 'exception_type' => $exception::class]);

            return false;
        }
    }
}
