<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Team;
use App\Models\User;
use App\Services\AccountStatusService;
use App\Services\EmployeeNumberService;
use App\Services\EmployeeWelcomeService;
use App\Services\TraineeEmployeeAccountService;
use App\Services\TraineeTeamService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TraineeController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403);

        return Inertia::render('trainees', [
            'assignmentTeams' => Team::with(['campaign:id,name', 'leaderAssignment.user:id,name'])->whereHas('leaderAssignment.user', fn ($q) => $q->where('role', 'team_leader')->where('status', 'active'))->orderBy('name')->get()->map(fn ($team) => ['id' => $team->id, 'name' => $team->name, 'campaign_id' => $team->campaign_id, 'leader' => $team->leaderAssignment->user->name]),
            'nextTraineeId' => app(EmployeeNumberService::class)->next(trainee: true),
            'trainingCampaigns' => Campaign::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'trainees' => User::whereNotNull('training_status')->with(['trainingCampaign:id,name', 'campaignSchedule:id,name', 'employeeAccount:id,username,source_trainee_id', 'teamMembership.team.leaderAssignment.user:id,name'])->orderByDesc('id')->paginate(20)->through(fn ($user) => [
                'id' => $user->id, 'name' => $user->name, 'username' => $user->username,
                'trainingCampaignId' => $user->training_campaign_id, 'teamId' => $user->teamMembership?->team_id, 'teamName' => $user->teamMembership?->team?->name, 'leaderName' => $user->teamMembership?->team?->leaderAssignment?->user?->name,
                'canAssign' => $user->role === 'trainee' && $user->canAccessAccount(),
                'campaign' => $user->trainingCampaign?->name, 'schedule' => $user->campaignSchedule?->name,
                'status' => $user->training_status, 'notes' => $user->training_review_notes, 'reviewed_at' => $user->training_reviewed_at,
                'employeeUsername' => $user->employeeAccount?->username,
                'canCreateEmployee' => $user->role === 'trainee' && $user->training_status === 'graduated' && ! $user->employeeAccount,
            ]),
            'statusMessage' => $request->session()->get('status'),
        ]);
    }

    public function review(Request $request, User $trainee, AccountStatusService $service)
    {
        abort_unless($request->user()->role === 'admin', 403);
        abort_unless($trainee->role === 'trainee', 409, 'This is a legacy employee record. Use Employee Status for this account.');
        $data = $request->validate(['decision' => ['required', Rule::in(['in_training', 'graduated', 'rejected'])], 'notes' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000']]);
        $service->update($request->user(), $trainee, $data['decision'], $data['notes'] ?? null);

        return back()->with('status', $data['decision'] === 'graduated' ? 'Trainee marked Passed. Their trainee account and records are retained. You can now create a separate employee account.' : 'Trainee status updated.');
    }

    public function assignTeam(Request $request, User $trainee, TraineeTeamService $service)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate(['team_id' => ['required', 'integer', 'exists:teams,id']]);
        $service->assign($request->user(), $trainee, Team::findOrFail($data['team_id']));

        return back()->with('status', 'Trainee assigned. The team leader can now view their team records and manage attendance.');
    }

    public function createEmployee(Request $request, User $trainee, TraineeEmployeeAccountService $service)
    {
        $employee = $service->create($request->user(), $trainee);
        $sent = app(EmployeeWelcomeService::class)->send($employee, $employee->username);

        return back()->with('status', 'Employee account '.$employee->username.' created. The trainee account is unchanged. Use the separate IDs to sign in. '.($sent ? 'Credentials were emailed.' : 'The credentials email could not be sent; the temporary password matches the new employee ID.'));
    }
}
