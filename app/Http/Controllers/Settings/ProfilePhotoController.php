<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProfilePhotoController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096']]);
        $path = $request->file('photo')->store('profile-photos', 'local');
        if (! $path) {
            throw ValidationException::withMessages(['photo' => 'The photo could not be saved. Please try again.']);
        }
        try {
            $previous = DB::transaction(function () use ($request, $path) {
                $user = User::lockForUpdate()->findOrFail($request->user()->id);
                $previous = $user->profile_photo_path;
                $user->profile_photo_path = $path;
                $user->save();

                return $previous;
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        if ($previous) {
            Storage::disk('local')->delete($previous);
        }

        return to_route('profile.edit')->with('status', 'Profile photo updated.');
    }

    public function destroy(Request $request)
    {
        $previous = DB::transaction(function () use ($request) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            $previous = $user->profile_photo_path;
            $user->profile_photo_path = null;
            $user->save();

            return $previous;
        });
        if ($previous) {
            Storage::disk('local')->delete($previous);
        }

        return to_route('profile.edit')->with('status', 'Profile photo removed.');
    }

    public function show(User $user)
    {
        abort_unless($user->profile_photo_path && Storage::disk('local')->exists($user->profile_photo_path), 404);

        return response()->file(Storage::disk('local')->path($user->profile_photo_path), ['Cache-Control' => 'private, no-cache', 'X-Content-Type-Options' => 'nosniff']);
    }
}
