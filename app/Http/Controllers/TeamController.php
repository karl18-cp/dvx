<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $filters = $request->validate([
            'campaign' => ['nullable', 'integer', Rule::exists('campaigns', 'id')],
        ]);
        $campaignId = isset($filters['campaign']) ? (int) $filters['campaign'] : null;

        return Inertia::render('teams', [
            'teams' => Team::query()
                ->with(['campaign:id,name,abbreviation', 'leaderAssignment.user:id,name,username'])
                ->when($campaignId, fn ($query, int $id) => $query->where('campaign_id', $id))
                ->orderBy('name')
                ->get()
                ->map(fn (Team $team): array => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'campaignId' => $team->campaign_id,
                    'campaign' => $team->campaign->name,
                    'campaignAbbreviation' => $team->campaign->abbreviation,
                    'teamLeader' => $team->leaderAssignment?->user?->name,
                    'teamLeaderEmployeeId' => $team->leaderAssignment?->user?->username,
                ]),
            'campaigns' => Campaign::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'filters' => ['campaign' => $campaignId],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        Team::query()->create($this->validatedData($request));

        return to_route('teams')->with('status', 'Team created successfully.');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $team->update($this->validatedData($request, $team));

        return to_route('teams')->with('status', 'Team updated successfully.');
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $team->delete();

        return to_route('teams')->with('status', 'Team deleted successfully.');
    }

    /** @return array{name: string, campaign_id: int} */
    private function validatedData(Request $request, ?Team $team = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')->ignore($team)],
            'campaign_id' => ['required', 'integer', Rule::exists('campaigns', 'id')],
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'manager'], true), 403);
    }
}
