<?php

namespace Tests\Feature;

use App\Models\{Announcement, CallEvaluation, Campaign, CoachingRecord, EmployeeForm, EmployeeFormResponse, Team, TeamLeaderAssignment, TeamMember, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamLeaderAccessTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $leader = User::factory()->create(['role' => 'team_leader', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $agents = [];
        $teams = [];
        foreach (['Alpha', 'Beta'] as $name) {
            $campaign = Campaign::create(['name' => $name, 'abbreviation' => $name]);
            $team = Team::create(['name' => $name, 'campaign_id' => $campaign->id]);
            $agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
            TeamMember::create(['user_id' => $agent->id, 'team_id' => $team->id]);
            $agents[] = $agent;
            $teams[] = $team;
        }
        TeamLeaderAssignment::create(['user_id' => $leader->id, 'team_id' => $teams[0]->id]);
        return [$leader, $admin, ...$agents, ...$teams];
    }

    public function test_dashboard_and_ranking_only_include_current_agents_and_no_team_means_no_global_fallback(): void
    {
        [$leader, $admin, $own, $other, $team] = $this->context();
        Announcement::create(['title' => 'For everyone', 'body' => 'Company update', 'author_name' => $admin->name]);
        $form = EmployeeForm::create(['title' => 'Points', 'fields' => []]);
        foreach ([$own, $other] as $employee) {
            EmployeeFormResponse::create(['request_id' => Str::uuid(), 'employee_form_id' => $form->id, 'employee_id' => $employee->id, 'employee_name' => $employee->name, 'form_title' => 'Points', 'revision' => 1, 'teams' => [], 'answers' => [], 'points' => 1]);
            $employee->personalInformation()->create(['email' => $employee->email, 'birth_date' => now('Asia/Manila')->subYears(25)->toDateString(), 'start_date' => now('Asia/Manila')->subYears(2)->toDateString(), 'gender' => 'Other', 'civil_status' => 'Single', 'phone' => '123', 'address' => 'Address', 'emergency_contact_name' => 'Contact', 'emergency_contact_relationship' => 'Parent', 'emergency_contact_phone' => '123']);
        }
        $this->actingAs($leader)->get('/dashboard')->assertInertia(fn (Assert $p) => $p->has('leaders', 1)->where('leaders.0.id', $own->id)->has('birthdays', 1)->where('birthdays.0.id', $own->id)->has('anniversaries', 1)->has('announcements', 1));
        $this->get('/ranking')->assertInertia(fn (Assert $p) => $p->has('leaders', 1)->where('leaders.0.id', $own->id));
        TeamLeaderAssignment::where('team_id', $team->id)->delete();
        $this->get('/dashboard')->assertInertia(fn (Assert $p) => $p->has('leaders', 0)->has('birthdays', 0)->has('anniversaries', 0)->has('announcements', 1));
        $this->get('/ranking')->assertInertia(fn (Assert $p) => $p->has('leaders', 0));
        $this->actingAs($admin)->get('/ranking')->assertInertia(fn (Assert $p) => $p->has('leaders', 2));
    }

    public function test_chat_search_direct_urls_and_unread_follow_current_membership(): void
    {
        [$leader, , $own, $other, , $otherTeam] = $this->context();
        $this->actingAs($leader)->getJson('/divertext/people')->assertJsonCount(1)->assertJsonPath('0.id', $own->id);
        $this->postJson('/divertext/direct', ['user_id' => $other->id])->assertForbidden();
        $room = $this->postJson('/divertext/direct', ['user_id' => $own->id])->assertOk()->json('id');
        $this->actingAs($own)->postJson("/divertext/$room/messages", ['request_id' => Str::uuid(), 'body' => 'Team message'])->assertOk();
        $this->actingAs($leader)->getJson('/divertext/unread')->assertJsonPath('count', 1);
        TeamMember::where('user_id', $own->id)->update(['team_id' => $otherTeam->id]);
        $this->getJson('/divertext/people')->assertExactJson([]);
        $this->getJson("/divertext/$room/messages")->assertForbidden();
        $this->postJson("/divertext/$room/messages", ['request_id' => Str::uuid(), 'body' => 'No'])->assertForbidden();
        $this->patchJson("/divertext/$room/read", ['message_id' => 1])->assertForbidden();
        $this->getJson('/divertext/unread')->assertJsonPath('count', 0);
    }

    public function test_qa_coaching_filters_exports_and_old_records_cannot_escape_current_team_scope(): void
    {
        [$leader, $admin, $own, $other, $team, $otherTeam] = $this->context();
        $records = [];
        foreach ([[$own, $team], [$other, $otherTeam]] as [$employee, $group]) {
            $evaluation = CallEvaluation::create(['employee_id' => $employee->id, 'evaluator_id' => $admin->id, 'campaign_id' => $group->campaign_id, 'campaign_name' => $group->name, 'team_id' => $group->id, 'team_name' => $group->name, 'scorecard_name' => 'QA Card', 'scorecard_snapshot' => ['categories' => []], 'call_at' => now(), 'call_direction' => 'inbound', 'status' => 'submitted', 'percentage' => 90, 'result' => 'passed', 'finalized_at' => now(), 'finalized_by' => $admin->id]);
            $coaching = CoachingRecord::create(['employee_id' => $employee->id, 'coach_id' => $admin->id, 'campaign_id' => $group->campaign_id, 'campaign_name' => $group->name, 'team_id' => $group->id, 'team_name' => $group->name, 'call_evaluation_id' => $evaluation->id, 'coaching_date' => today(), 'type' => 'General Coaching', 'status' => 'open', 'summary' => 'Team coaching']);
            $records[] = [$evaluation, $coaching];
        }
        $this->actingAs($leader)->get('/management/call-evaluations')->assertInertia(fn (Assert $p) => $p->has('evaluations.data', 1)->has('campaigns', 1)->where('campaigns.0.id', $team->campaign_id)->has('teams', 1)->has('employees', 1));
        $this->get('/management/coaching')->assertInertia(fn (Assert $p) => $p->has('records.data', 1)->has('campaigns', 1)->has('teams', 1)->has('materials', 0));
        $this->get('/management/call-evaluations?team='.$otherTeam->id)->assertInertia(fn (Assert $p) => $p->has('evaluations.data', 0));
        $export = $this->get('/management/qa-dashboard/export')->assertOk()->streamedContent();
        $this->assertStringContainsString($own->name, $export);
        $this->assertStringNotContainsString($other->name, $export);
        foreach ($records as $index => [$evaluation, $coaching]) {
            $response = $this->get("/management/call-evaluations/$evaluation->id");
            $index === 0 ? $response->assertOk() : $response->assertForbidden();
        }
        TeamMember::where('user_id', $own->id)->update(['team_id' => $otherTeam->id]);
        [$evaluation, $coaching] = $records[0];
        foreach (["/management/call-evaluations/$evaluation->id", "/management/call-evaluations/$evaluation->id/recording", "/management/call-evaluations/$evaluation->id/document", "/management/coaching/$coaching->id", "/management/coaching/$coaching->id/export", "/management/employees/$own->id/training-profile"] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->get('/management/qa-dashboard')->assertInertia(fn (Assert $p) => $p->where('summary.calls_evaluated', 0));
        foreach (['/employees', '/forms', '/requests', '/team-assigning', '/campaigns', '/teams', '/management/assessment-results'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }
}
