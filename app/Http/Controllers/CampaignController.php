<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('campaigns', [
            'campaigns' => Campaign::query()
                ->orderBy('name')
                ->withCount(['teams', 'assessments', 'trainingMaterials', 'bankQuestions'])
                ->orderByDesc('is_active')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $campaign = Campaign::query()->create($this->validatedData($request));
        $this->log($request, 'Campaign Created', $campaign);

        return to_route('campaigns')->with('status', 'Campaign created successfully.');
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $campaign->update($this->validatedData($request, $campaign));
        $this->log($request, $campaign->is_active ? 'Campaign Updated' : 'Campaign Deactivated', $campaign);

        return to_route('campaigns')->with('status', 'Campaign updated successfully.');
    }

    public function destroy(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_if($campaign->teams()->exists() || $campaign->assessments()->exists() || $campaign->trainingMaterials()->exists() || $campaign->bankQuestions()->exists(), 422, 'Campaigns with related or historical records must be deactivated, not deleted.');
        $campaign->delete();

        return to_route('campaigns')->with('status', 'Unused campaign deleted.');
    }

    /** @return array{name: string, abbreviation: string} */
    private function validatedData(Request $request, ?Campaign $campaign = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('campaigns', 'name')->ignore($campaign)],
            'abbreviation' => ['required', 'string', 'max:30', Rule::unique('campaigns', 'abbreviation')->ignore($campaign)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function log(Request $request, string $action, Campaign $campaign): void
    {
        DB::table('assessment_activity_logs')->insert(['actor_id' => $request->user()->id, 'action' => $action, 'target_type' => Campaign::class, 'target_id' => $campaign->id, 'metadata' => null, 'created_at' => now()]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'manager'], true), 403);
    }
}
