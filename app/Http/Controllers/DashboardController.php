<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use App\Services\EmployeeRankingService;
use App\Services\TeamLeaderWorkspaceService;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request, EmployeeRankingService $ranking, TeamLeaderWorkspaceService $workspace)
    {
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $end = $today->addDays(29);
        $birthdays = collect();
        $anniversaries = collect();
        $employees = User::where('status', 'active')->whereHas('personalInformation')
            ->when($request->user()->role === 'team_leader', fn ($q) => $q->where(fn ($q) => $q->whereKey($request->user()->id)->orWhereIn('id', $workspace->members($request->user())->select('users.id'))))
            ->select(['id', 'name', 'username', 'profile_photo_path'])
            ->with('personalInformation:id,user_id,birth_date,start_date')->get();
        foreach ($employees as $employee) {
            foreach (['birth_date', 'start_date'] as $field) {
                $source = $employee->personalInformation?->$field;
                if (! $source || $source->toDateString() > $today->toDateString()) {
                    continue;
                }
                $date = $this->occurrence($source, $today->year);
                if ($date->lt($today)) {
                    $date = $this->occurrence($source, $today->year + 1);
                }
                $years = $date->year - $source->year;
                if ($date->gt($end) || ($field === 'start_date' && $years < 1)) {
                    continue;
                }
                $event = ['id' => $employee->id, 'name' => $employee->name, 'avatar' => $employee->avatar, 'date' => $date->toDateString(), 'daysAway' => (int) $today->diffInDays($date)];
                if ($field === 'start_date') {
                    $anniversaries->push([...$event, 'years' => $years]);
                } else {
                    $birthdays->push($event);
                }
            }
        }

        return Inertia::render('dashboard', [
            'teamLeaderSummary' => $request->user()->role === 'team_leader' ? $workspace->summary($request->user()) : null,
            'today' => $today->toDateString(), 'leaders' => $ranking->leaders(5, $request->user()),
            'birthdays' => $birthdays->sortBy([['date', 'asc'], ['name', 'asc']])->values(),
            'anniversaries' => $anniversaries->sortBy([['date', 'asc'], ['name', 'asc']])->values(),
            'announcements' => Announcement::latest('id')->limit(5)->get(['id', 'title', 'body', 'author_name', 'created_at']),
        ]);
    }

    private function occurrence(CarbonInterface $source, int $year): CarbonImmutable
    {
        $month = CarbonImmutable::create($year, $source->month, 1, 0, 0, 0, 'Asia/Manila');

        // Observe February 29 celebrations on February 28 in non-leap years.
        return $month->day(min($source->day, $month->daysInMonth));
    }
}
