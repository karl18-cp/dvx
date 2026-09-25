<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AttendanceFaceVerifier;
use App\Services\PersonalAttendanceClock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PersonalAttendanceController extends Controller
{
    private function actor(Request $request): User
    {
        $user = $request->user()->fresh();
        abort_unless($user->role === 'team_leader' && $user->status === 'active', 403);

        return $user;
    }

    public function index(Request $request, PersonalAttendanceClock $clock)
    {
        return Inertia::render('my-attendance', ['clock' => $clock->context($this->actor($request))]);
    }

    public function status(Request $request, PersonalAttendanceClock $clock)
    {
        return response()->json($clock->context($this->actor($request)));
    }

    public function challenge(Request $request, PersonalAttendanceClock $clock)
    {
        $user = $this->actor($request);
        $data = $request->validate(['action' => ['required', Rule::in(['time_in', 'time_out'])]]);
        $context = $clock->assertAction($user, $data['action']);
        if (! $context['faceEnrolled']) {
            throw ValidationException::withMessages(['face' => 'Ask an administrator to enroll your face before using the attendance clock.']);
        }
        $id = (string) Str::uuid();
        $direction = random_int(0, 1) ? 'left' : 'right';
        DB::transaction(function () use ($user, $request, $id, $direction, $context, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            DB::table('attendance_clock_challenges')->where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);
            DB::table('attendance_clock_challenges')->insert(['id' => $id, 'user_id' => $user->id, 'session_hash' => hash('sha256', $request->session()->getId()), 'action' => $data['action'], 'attendance_date' => $context['date'], 'direction' => $direction, 'expires_at' => now()->addSeconds(config('face.challenge_seconds')), 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['id' => $id, 'direction' => $direction, 'expiresIn' => config('face.challenge_seconds')]);
    }

    public function verify(Request $request, PersonalAttendanceClock $clock, AttendanceFaceVerifier $verifier)
    {
        $user = $this->actor($request);
        $data = $request->validate(['challenge_id' => ['required', 'uuid'], 'frames' => ['required', 'array', 'size:3'], 'frames.*' => ['required', 'string', 'max:500000', 'regex:/^data:image\/jpeg;base64,[A-Za-z0-9+\/=]+$/D']]);
        $challenge = DB::transaction(function () use ($data, $request, $user) {
            $challenge = DB::table('attendance_clock_challenges')->where('id', $data['challenge_id'])->lockForUpdate()->first();
            if (! $challenge || $challenge->user_id !== $user->id || ! hash_equals($challenge->session_hash, hash('sha256', $request->session()->getId())) || $challenge->used_at || now()->gte($challenge->expires_at)) {
                throw ValidationException::withMessages(['face' => 'This verification expired or was already used. Start a new face check.']);
            }
            DB::table('attendance_clock_challenges')->where('id', $challenge->id)->update(['used_at' => now()]);

            return $challenge;
        });
        $clock->assertAction($user, $challenge->action, $challenge->attendance_date);
        $credentialId = $user->faceCredential?->id;
        $credentialUpdated = $user->faceCredential?->updated_at?->toISOString();
        $verifier->verify($user, $data['frames'], $challenge->direction);
        DB::transaction(function () use ($user, $clock, $challenge, $credentialId, $credentialUpdated) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->role === 'team_leader' && $locked->status === 'active', 403);
            if (now()->gte($challenge->expires_at) || $locked->faceCredential?->id !== $credentialId || $locked->faceCredential?->updated_at?->toISOString() !== $credentialUpdated) {
                throw ValidationException::withMessages(['face' => 'Your verification expired or enrollment changed. Please retry.']);
            }
            $clock->assertAction($locked, $challenge->action, $challenge->attendance_date);
            $clock->record($locked, $challenge->action, $challenge->attendance_date);
            $locked->faceCredential->update(['last_verified_at' => now()]);
            DB::table('attendance_clock_challenges')->where('id', $challenge->id)->update(['accepted_at' => now()]);
        });

        return response()->json(['message' => 'Face verified. Attendance recorded.', 'clock' => $clock->context($user->fresh())]);
    }

    public function breakPunch(Request $request, PersonalAttendanceClock $clock)
    {
        $user = $this->actor($request);
        $data = $request->validate(['action' => ['required', Rule::in(['lunch_out', 'lunch_in'])]]);
        DB::transaction(function () use ($user, $data, $clock) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->role === 'team_leader' && $locked->status === 'active', 403);
            $context = $clock->assertAction($locked, $data['action']);
            $clock->record($locked, $data['action'], $context['date']);
        });

        return response()->json(['message' => 'Break time recorded.', 'clock' => $clock->context($user->fresh())]);
    }
}
