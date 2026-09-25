<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TraineeController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403);

        return Inertia::render('trainees', [
            'nextTraineeId' => app(\App\Services\EmployeeNumberService::class)->next(trainee: true),
            'trainingCampaigns' => \App\Models\Campaign::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'trainees' => User::query()->whereNotNull('training_status')
                ->with(['trainingCampaign:id,name', 'campaignSchedule:id,name'])
                ->orderByDesc('id')->paginate(20)->through(fn ($user) => [
                    'id' => $user->id, 'name' => $user->name, 'username' => $user->username,
                    'campaign' => $user->trainingCampaign?->name,
                    'schedule' => $user->campaignSchedule?->name,
                    'status' => $user->training_status,
                    'notes' => $user->training_review_notes,
                    'reviewed_at' => $user->training_reviewed_at,
                ]),
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function review(Request $request, User $trainee)
    {
        abort_unless($request->user()->role === 'admin' && $request->user()->status === 'active', 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['graduated', 'rejected'])],
            'notes' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000'],
        ]);
        $reviewed = DB::transaction(function () use ($request, $trainee, $data) {
            $trainee = User::query()->lockForUpdate()->findOrFail($trainee->id);
            abort_unless($trainee->role === 'trainee' && $trainee->training_status === 'in_training', 409, 'This trainee has already been reviewed.');
            $previousUsername = $trainee->username;
            $officialUsername = $data['decision'] === 'graduated'
                ? app(\App\Services\EmployeeNumberService::class)->next(allocate: true)
                : $trainee->username;
            $trainee->forceFill([
                'username' => $officialUsername,
                ...($data['decision'] === 'graduated' && \Illuminate\Support\Facades\Hash::check((string) $previousUsername, $trainee->password)
                    ? ['password' => $officialUsername] : []),
                'role' => $data['decision'] === 'graduated' ? 'agent' : 'trainee',
                'status' => $data['decision'] === 'graduated' ? 'active' : 'terminated',
                'training_status' => $data['decision'],
                'training_reviewed_by' => $request->user()->id,
                'training_reviewed_at' => now(),
                'training_review_notes' => $data['notes'] ?? null,
            ])->save();
            DB::table('assessment_activity_logs')->insert([
                'actor_id' => $request->user()->id, 'action' => 'trainee.'.$data['decision'],
                'target_type' => User::class, 'target_id' => $trainee->id,
                'metadata' => json_encode(['previous_username' => $previousUsername, 'username' => $officialUsername, 'campaign_id' => $trainee->training_campaign_id, 'notes' => $data['notes'] ?? null]), 'created_at' => now(),
            ]);

            return $trainee;
        }, attempts: 3);

        if ($data['decision'] === 'graduated') {
            $sent = app(\App\Services\EmployeeWelcomeService::class)->sendGraduation($reviewed);

            return back()->with('status', 'Trainee graduated to Agent as '.$reviewed->username.'. Attendance and coaching history are preserved. An unchanged temporary password now matches the new ID; a changed password is retained. '.($sent ? 'Their new employee ID was emailed to them.' : 'The new ID email could not be sent; please inform the employee of their new username.').' Assign their production team in Team Assigning.');
        }

        return back()->with('status', $data['decision'] === 'graduated' ? 'Trainee graduated to Agent. Their attendance and coaching history are preserved. Assign their production team in Team Assigning.' : 'Trainee rejected. Their access has ended and history is retained.');
    }
}
