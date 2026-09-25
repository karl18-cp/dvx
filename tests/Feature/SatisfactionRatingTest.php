<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\SatisfactionRating;
use App\Models\Team;
use App\Models\TeamLeaderAssignment;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SatisfactionRatingTest extends TestCase
{
    use RefreshDatabase;

    private function data(User $employee): array
    {
        return ['request_id' => (string) Str::uuid(), 'employee_id' => $employee->id, 'quality' => 5, 'productivity' => 3, 'attendance' => 2, 'communication' => 1, 'professionalism' => 3, 'comments' => 'Follow up on communication.', 'average' => 5];
    }

    public function test_admin_can_rate_every_employee_role_and_average_is_calculated_on_server(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        foreach (['agent', 'team_leader', 'manager', 'admin', 'it_admin', 'it_support', 'it_developer'] as $role) {
            $employee = User::factory()->create(['role' => $role]);
            $this->post('/satisfaction-results', $this->data($employee))->assertSessionHasNoErrors()->assertRedirect('/satisfaction-results');
            $this->assertDatabaseHas('satisfaction_ratings', ['employee_id' => $employee->id, 'rated_by' => $admin->id, 'employee_role' => $role, 'average' => 2.8]);
        }
        $this->assertDatabaseCount('satisfaction_ratings', 7);
        $this->assertDatabaseCount('assessment_activity_logs', 7);
        $this->get('/satisfaction-results')->assertInertia(fn (Assert $page) => $page->component('satisfaction-results')->where('records.total', 7)->has('employees', 8));
    }

    public function test_non_admins_cannot_read_or_submit_ratings(): void
    {
        $employee = User::factory()->create(['role' => 'agent']);
        foreach (['agent', 'team_leader', 'manager', 'it_admin', 'it_support', 'it_developer'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/satisfaction-results')->assertForbidden();
            $this->post('/satisfaction-results', $this->data($employee))->assertForbidden();
        }
        $this->assertDatabaseCount('satisfaction_ratings', 0);
    }

    public function test_all_five_scores_are_required_and_must_be_integers_from_one_to_five(): void
    {
        $employee = User::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (SatisfactionRating::CATEGORIES as $category) {
            foreach ([null, 0, 6, 2.5, 'bad'] as $value) {
                $this->post('/satisfaction-results', [...$this->data($employee), $category => $value])->assertSessionHasErrors($category);
            }
        }
        $this->post('/satisfaction-results', [...$this->data($employee), 'employee_id' => 99999])->assertSessionHasErrors('employee_id');
        $this->post('/satisfaction-results', [...$this->data($employee), 'comments' => str_repeat('x', 5001)])->assertSessionHasErrors('comments');
        $this->assertDatabaseCount('satisfaction_ratings', 0);
    }

    public function test_retries_do_not_duplicate_ratings_and_token_reuse_cannot_modify_records(): void
    {
        $employee = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $data = $this->data($employee);
        $this->actingAs($admin)->post('/satisfaction-results', $data)->assertSessionHasNoErrors();
        $this->post('/satisfaction-results', $data)->assertSessionHasNoErrors();
        $this->post('/satisfaction-results', [...$data, 'quality' => 1])->assertSessionHasErrors('request_id');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/satisfaction-results', $data)->assertSessionHasErrors('request_id');
        $this->assertDatabaseCount('satisfaction_ratings', 1);
        $this->assertDatabaseCount('assessment_activity_logs', 1);
        $this->assertSame('2.80', SatisfactionRating::firstOrFail()->average);
    }

    public function test_employee_and_team_snapshots_are_preserved_and_leader_teams_are_included(): void
    {
        $campaign = Campaign::create(['name' => 'Home Improvement', 'abbreviation' => 'HI']);
        $team = Team::create(['name' => 'Jungle', 'campaign_id' => $campaign->id]);
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Original Reviewer']);
        $leader = User::factory()->create(['role' => 'team_leader', 'name' => 'Original Leader']);
        $agent = User::factory()->create(['role' => 'agent']);
        TeamLeaderAssignment::create(['team_id' => $team->id, 'user_id' => $leader->id]);
        TeamMember::create(['team_id' => $team->id, 'user_id' => $agent->id]);
        $this->actingAs($admin)->post('/satisfaction-results', $this->data($leader))->assertSessionHasNoErrors();
        $this->post('/satisfaction-results', $this->data($agent))->assertSessionHasNoErrors();
        $leader->update(['name' => 'Changed', 'role' => 'agent']);
        $admin->update(['name' => 'Changed Reviewer']);
        $team->update(['name' => 'New team name']);
        $record = SatisfactionRating::where('employee_id', $leader->id)->firstOrFail();
        $this->assertSame('Original Leader', $record->employee_name);
        $this->assertSame('Original Reviewer', $record->reviewer_name);
        $this->assertSame('team_leader', $record->employee_role);
        $this->assertSame([['name' => 'Jungle', 'campaign' => 'Home Improvement']], $record->teams);
        $this->assertSame($record->teams, SatisfactionRating::where('employee_id', $agent->id)->firstOrFail()->teams);
    }

    public function test_results_support_search_role_filters_and_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['name' => 'Find Me', 'role' => 'team_leader']);
        $this->actingAs($admin)->post('/satisfaction-results', $this->data($employee))->assertSessionHasNoErrors();
        $record = SatisfactionRating::firstOrFail();
        for ($i = 0; $i < 20; $i++) {
            $copy = $record->replicate();
            $copy->request_id = (string) Str::uuid();
            $copy->save();
        }
        $this->get('/satisfaction-results?search=Find&role=team_leader')->assertInertia(fn (Assert $page) => $page->where('records.total', 21)->has('records.data', 20));
        $this->get('/satisfaction-results?page=2')->assertInertia(fn (Assert $page) => $page->has('records.data', 1));
        $this->get('/satisfaction-results?role=agent')->assertInertia(fn (Assert $page) => $page->where('records.total', 0));
        $this->get('/satisfaction-results?search=missing')->assertInertia(fn (Assert $page) => $page->where('records.total', 0));
    }
}
