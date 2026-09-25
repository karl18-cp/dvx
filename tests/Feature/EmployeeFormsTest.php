<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\EmployeeForm;
use App\Models\EmployeeFormResponse;
use App\Models\Team;
use App\Models\TeamLeaderAssignment;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeFormsTest extends TestCase
{
    use RefreshDatabase;

    private function team(): Team
    {
        return Team::create(['name' => 'Team One', 'campaign_id' => Campaign::create(['name' => 'Test campaign', 'abbreviation' => 'TC'])->id]);
    }

    private function data(Team $team): array
    {
        return ['title' => 'Daily Report', 'description' => 'Report your work.', 'status' => 'active', 'ranking_enabled' => true, 'team_ids' => [$team->id], 'fields' => [
            ['id' => (string) Str::uuid(), 'label' => 'Client name', 'type' => 'text', 'placeholder' => '', 'required' => true, 'options' => []],
            ['id' => (string) Str::uuid(), 'label' => 'Products', 'type' => 'checkbox', 'placeholder' => '', 'required' => true, 'options' => ['Life', 'Medical']],
            ['id' => (string) Str::uuid(), 'label' => 'Outcome', 'type' => 'dropdown', 'placeholder' => '', 'required' => true, 'options' => ['Success', 'Follow up']],
        ]];
    }

    private function createForm(Team $team): EmployeeForm
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/forms', $this->data($team))->assertSessionHasNoErrors()->assertRedirect('/forms');

        return EmployeeForm::latest('id')->firstOrFail();
    }

    private function responseData(EmployeeForm $form): array
    {
        return ['request_id' => (string) Str::uuid(), 'revision' => $form->revision, 'answers' => [$form->fields[0]['id'] => 'Jane Client', $form->fields[1]['id'] => ['Life'], $form->fields[2]['id'] => 'Success']];
    }

    public function test_admin_manages_forms_and_only_assigned_members_or_leaders_can_respond(): void
    {
        $team = $this->team();
        $form = $this->createForm($team);
        $member = User::factory()->create(['role' => 'agent']);
        TeamMember::create(['team_id' => $team->id, 'user_id' => $member->id]);
        $leader = User::factory()->create(['role' => 'team_leader']);
        TeamLeaderAssignment::create(['team_id' => $team->id, 'user_id' => $leader->id]);
        foreach ([$member, $leader] as $user) {
            $this->actingAs($user)->get('/my-forms')->assertInertia(fn (Assert $page) => $page->component('forms/mine')->has('forms', 1));
            $this->post("/my-forms/$form->id/responses", $this->responseData($form))->assertSessionHasNoErrors()->assertRedirect('/my-forms');
            $this->get('/forms')->assertForbidden();
            $this->post('/forms', $this->data($team))->assertForbidden();
            $this->put("/forms/$form->id", $this->data($team))->assertForbidden();
            $this->delete("/forms/$form->id")->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'manager']))->get('/forms?tab=responses')->assertForbidden();
        $outsider = User::factory()->create(['role' => 'agent']);
        $this->actingAs($outsider)->get('/my-forms')->assertInertia(fn (Assert $page) => $page->has('forms', 0));
        $this->post("/my-forms/$form->id/responses", $this->responseData($form))->assertForbidden();
        $this->assertDatabaseCount('employee_form_responses', 2);
    }

    public function test_required_answers_and_options_are_validated_and_stale_or_inactive_forms_are_rejected(): void
    {
        $team = $this->team();
        $form = $this->createForm($team);
        $employee = User::factory()->create(['role' => 'agent']);
        TeamMember::create(['team_id' => $team->id, 'user_id' => $employee->id]);
        $this->actingAs($employee);
        $data = $this->responseData($form);
        $bad = $data;
        $bad['answers'][$form->fields[0]['id']] = '';
        $bad['answers'][$form->fields[1]['id']] = ['Not an option'];
        $bad['answers'][$form->fields[2]['id']] = 'Forged';
        $this->post("/my-forms/$form->id/responses", $bad)->assertSessionHasErrors();
        $this->post("/my-forms/$form->id/responses", [...$data, 'answers' => ['unknown' => 'value']])->assertSessionHasErrors('answers');
        $form->update(['revision' => 2]);
        $this->post("/my-forms/$form->id/responses", $data)->assertSessionHasErrors('revision');
        $form->update(['status' => 'inactive']);
        $this->post("/my-forms/$form->id/responses", [...$data, 'revision' => 2])->assertForbidden();
        $this->get('/my-forms')->assertInertia(fn (Assert $page) => $page->has('forms', 0));
        $this->assertDatabaseCount('employee_form_responses', 0);
    }

    public function test_responses_award_one_point_and_retries_do_not_duplicate_answers_or_points(): void
    {
        $team = $this->team();
        $form = $this->createForm($team);
        $employee = User::factory()->create(['role' => 'agent']);
        TeamMember::create(['team_id' => $team->id, 'user_id' => $employee->id]);
        $data = $this->responseData($form);
        $this->actingAs($employee)->post("/my-forms/$form->id/responses", $data)->assertSessionHasNoErrors();
        $this->post("/my-forms/$form->id/responses", $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('employee_form_responses', 1);
        $this->assertDatabaseHas('employee_form_responses', ['employee_id' => $employee->id, 'points' => 1]);
        $bad = $data;
        $bad['answers'][$form->fields[0]['id']] = 'Different person';
        $this->post("/my-forms/$form->id/responses", $bad)->assertSessionHasErrors('request_id');
        $this->post("/my-forms/$form->id/responses", [...$data, 'request_id' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->get('/ranking')->assertInertia(fn (Assert $page) => $page->component('forms/ranking')->has('leaders', 1)->where('leaders.0.points', 2));
        $form->update(['ranking_enabled' => false]);
        $this->post("/my-forms/$form->id/responses", [...$data, 'request_id' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('employee_form_responses', 3);
        $this->assertSame(2, (int) EmployeeFormResponse::sum('points'));
    }

    public function test_response_snapshots_survive_form_edit_deletion_and_employee_changes(): void
    {
        $team = $this->team();
        $form = $this->createForm($team);
        $employee = User::factory()->create(['role' => 'agent', 'name' => 'Original Employee']);
        TeamMember::create(['team_id' => $team->id, 'user_id' => $employee->id]);
        $this->actingAs($employee)->post("/my-forms/$form->id/responses", $this->responseData($form))->assertSessionHasNoErrors();
        $admin = User::factory()->create(['role' => 'admin']);
        $updated = [...$this->data($team), 'title' => 'New title', 'revision' => 1];
        $this->actingAs($admin)->put("/forms/$form->id", $updated)->assertSessionHasNoErrors();
        $this->put("/forms/$form->id", $updated)->assertSessionHasErrors('revision');
        $employee->update(['name' => 'Changed Employee']);
        $this->delete("/forms/$form->id")->assertRedirect('/forms');
        $this->assertSoftDeleted($form);
        $this->get("/forms?tab=responses&form_id=$form->id")->assertInertia(fn (Assert $page) => $page->component('forms/manage')->where('responses.total', 1)->where('responses.data.0.employee_name', 'Original Employee')->where('responses.data.0.form_title', 'Daily Report')->where('responses.data.0.answers.0.label', 'Client name')->where('responses.data.0.answers.0.value', 'Jane Client')->where('responses.data.0.points', 1));
    }

    public function test_builder_rejects_invalid_teams_fields_and_choices(): void
    {
        $team = $this->team();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $data = $this->data($team);
        $this->post('/forms', [...$data, 'team_ids' => []])->assertSessionHasErrors('team_ids');
        $this->post('/forms', [...$data, 'team_ids' => [99999]])->assertSessionHasErrors('team_ids.0');
        $this->post('/forms', [...$data, 'fields' => []])->assertSessionHasErrors('fields');
        $data['fields'][1]['options'] = ['Duplicate', 'Duplicate'];
        $this->post('/forms', $data)->assertSessionHasErrors('fields.1.options');
        $this->assertDatabaseCount('employee_forms', 0);
    }

    public function test_admin_can_filter_and_paginate_responses(): void
    {
        $team = $this->team();
        $form = $this->createForm($team);
        $employee = User::factory()->create(['role' => 'agent', 'name' => 'Searchable Employee']);
        TeamMember::create(['team_id' => $team->id, 'user_id' => $employee->id]);
        $this->actingAs($employee)->post("/my-forms/$form->id/responses", $this->responseData($form))->assertSessionHasNoErrors();
        $record = EmployeeFormResponse::firstOrFail();
        for ($i = 0; $i < 21; $i++) {
            $copy = $record->replicate();
            $copy->request_id = (string) Str::uuid();
            $copy->save();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/forms?tab=responses&search=Searchable')->assertInertia(fn (Assert $page) => $page->has('responses.data', 20)->where('responses.total', 22));
        $this->get('/forms?tab=responses&page=2')->assertInertia(fn (Assert $page) => $page->has('responses.data', 2));
        $this->get('/forms?tab=responses&search=missing')->assertInertia(fn (Assert $page) => $page->where('responses.total', 0));
        $this->get('/forms?tab=responses&to=2000-01-01')->assertSessionHasNoErrors()->assertInertia(fn (Assert $page) => $page->where('responses.total', 0));
    }

    public function test_typed_fields_reject_bad_values_and_optional_answers_can_be_retried(): void
    {
        $team = $this->team();
        $admin = User::factory()->create(['role' => 'admin']);
        $data = $this->data($team);
        $data['fields'] = array_map(fn ($type) => ['id' => (string) Str::uuid(), 'label' => $type, 'type' => $type, 'placeholder' => '', 'required' => $type !== 'textarea', 'options' => $type === 'radio' ? ['Yes', 'No'] : []], ['number', 'email', 'date', 'radio', 'textarea']);
        $this->actingAs($admin)->post('/forms', $data)->assertSessionHasNoErrors();
        $form = EmployeeForm::firstOrFail();
        $employee = User::factory()->create(['role' => 'agent']);
        TeamMember::create(['team_id' => $team->id, 'user_id' => $employee->id]);
        $fields = $form->fields;
        $answers = [$fields[0]['id'] => '12.5', $fields[1]['id'] => 'person@example.test', $fields[2]['id'] => '2026-09-24', $fields[3]['id'] => 'Yes'];
        $payload = ['request_id' => (string) Str::uuid(), 'revision' => '1', 'answers' => $answers, 'points' => 999];
        $this->actingAs($employee)->post("/my-forms/$form->id/responses", [...$payload, 'answers' => [$fields[0]['id'] => 'NaN', $fields[1]['id'] => 'bad email', $fields[2]['id'] => '2026-02-31', $fields[3]['id'] => 'Unknown']])->assertSessionHasErrors();
        $this->post("/my-forms/$form->id/responses", $payload)->assertSessionHasNoErrors();
        $this->post("/my-forms/$form->id/responses", $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('employee_form_responses', 1);
        $record = EmployeeFormResponse::firstOrFail();
        $this->assertSame(1, $record->points);
        $this->assertNull($record->answers[4]['value']);
        $record->created_at = '2026-09-23 17:00:00';
        $record->save();
        $this->actingAs($admin)->get('/forms?tab=responses&from=2026-09-24&to=2026-09-24')->assertInertia(fn (Assert $page) => $page->where('responses.total', 1));
    }
}
