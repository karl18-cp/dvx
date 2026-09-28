<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountIdentity
{
    public function resolve(string $identifier): ?User
    {
        $identifier = mb_strtolower(trim($identifier));
        $user = User::whereRaw('LOWER(username) = ?', [$identifier])->first();
        if ($user) {
            return $user;
        }
        $matches = User::whereRaw('LOWER(email) = ?', [$identifier])->limit(2)->get();
        if ($matches->count() > 1) {
            throw ValidationException::withMessages(['email' => 'Use your employee or trainee ID when accounts share an email address.']);
        }

        return $matches->first();
    }

    public static function emailRule(?User $user)
    {
        $ids = $user ? array_filter([$user->id, $user->source_trainee_id, $user->employeeAccount()->value('id')]) : [];

        return Rule::unique('users', 'email')->where(fn ($q) => $q->whereNotIn('id', $ids));
    }
}
