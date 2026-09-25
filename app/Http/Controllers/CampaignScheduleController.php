<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCampaignScheduleRequest;
use App\Models\CampaignSchedule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CampaignScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManager($request);

        return Inertia::render('campaign-schedules', [
            'schedules' => CampaignSchedule::query()->with('days')->withCount('employees')->orderBy('name')->get()->map(fn ($schedule) => [
                'id' => $schedule->id, 'name' => $schedule->name, 'employees_count' => $schedule->employees_count,
                'days' => $schedule->days->map(function ($day) {
                    $row = $day->only(['day', 'no_schedule', 'time_in', 'time_out', 'break_start', 'break_end']);
                    foreach (['time_in', 'time_out', 'break_start', 'break_end'] as $field) {
                        $row[$field] = $row[$field] ? substr($row[$field], 0, 5) : '';
                    }

                    return $row;
                }),
            ]),
            'employees' => User::query()->with(['campaignSchedule:id,name', 'teamMembership.team.campaign:id,name'])
                ->orderBy('name')->get(['id', 'name', 'username', 'role', 'status', 'campaign_schedule_id'])
                ->map(fn ($user) => [
                    'id' => $user->id, 'name' => $user->name, 'username' => $user->username, 'role' => $user->role, 'status' => $user->status,
                    'schedule_id' => $user->campaign_schedule_id, 'schedule_name' => $user->campaignSchedule?->name,
                    'team' => $user->teamMembership?->team?->name, 'campaign' => $user->teamMembership?->team?->campaign?->name,
                ]),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(SaveCampaignScheduleRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $schedule = CampaignSchedule::query()->create(['name' => $request->validated('name'), 'created_by' => $request->user()->id]);
            $schedule->days()->createMany($request->scheduleDays());
        });

        return to_route('campaign-schedules', ['tab' => 'view'])->with('status', 'Schedule created.');
    }

    public function update(SaveCampaignScheduleRequest $request, CampaignSchedule $schedule): RedirectResponse
    {
        DB::transaction(function () use ($request, $schedule): void {
            $schedule = CampaignSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            $schedule->update(['name' => $request->validated('name')]);
            $schedule->days()->delete();
            $schedule->days()->createMany($request->scheduleDays());
        });

        return to_route('campaign-schedules', ['tab' => 'view'])->with('status', 'Schedule updated for all assigned employees.');
    }

    public function assign(Request $request, CampaignSchedule $schedule): RedirectResponse
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'employee_ids' => ['present', 'array'],
            'employee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where(fn ($q) => $q->where('status', 'active')->orWhere('campaign_schedule_id', $schedule->id))],
        ]);
        DB::transaction(function () use ($schedule, $data): void {
            CampaignSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            User::query()->where(fn ($q) => $q->where('campaign_schedule_id', $schedule->id)->orWhereIn('id', $data['employee_ids']))->orderBy('id')->lockForUpdate()->get(['id']);
            User::query()->where('campaign_schedule_id', $schedule->id)->whereNotIn('id', $data['employee_ids'])->update(['campaign_schedule_id' => null]);
            User::query()->whereIn('id', $data['employee_ids'])->update(['campaign_schedule_id' => $schedule->id]);
        }, attempts: 3);

        return to_route('campaign-schedules', ['tab' => 'view'])->with('status', 'Employee assignments saved.');
    }

    public function destroy(Request $request, CampaignSchedule $schedule): RedirectResponse
    {
        $this->authorizeManager($request);
        $schedule->delete();

        return to_route('campaign-schedules', ['tab' => 'view'])->with('status', 'Schedule deleted. Its employee assignments were removed.');
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'manager'], true), 403);
    }
}
