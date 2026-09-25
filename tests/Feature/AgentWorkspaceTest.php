<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentNotification;
use App\Models\AttendanceRecord;
use App\Models\CampaignSchedule;
use App\Models\CoachingRecord;
use App\Models\EmployeeForm;
use App\Models\EmployeeFormResponse;
use App\Models\EmployeeSanction;
use App\Models\OvertimeRequest;
use App\Models\SatisfactionRating;
use App\Models\UndertimeRequest;
use App\Models\User;
use App\Services\AttendanceFaceVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgentWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function agent(): User
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-28 08:30', 'Asia/Manila'));

        return User::factory()->create(['role' => 'agent', 'status' => 'active']);
    }

    private function schedule(User $user): void
    {
        $schedule = CampaignSchedule::create(['name' => 'Agent shift']);
        foreach (range(1, 7) as $day) {
            $schedule->days()->create(['day' => $day, 'no_schedule' => false, 'time_in' => '08:00', 'time_out' => '17:00', 'break_start' => '12:00', 'break_end' => '13:00']);
        }
        $user->forceFill(['campaign_schedule_id' => $schedule->id])->save();
    }

    public function test_personal_records_are_private_but_dashboard_and_ranking_are_shared(): void
    {
        $own = $this->agent();
        $other = $this->agent();
        $form = EmployeeForm::create(['title' => 'Daily output', 'fields' => []]);
        foreach ([$own, $other] as $person) {
            EmployeeSanction::create(['request_id' => Str::uuid(), 'employee_id' => $person->id, 'employee_name' => $person->name, 'employee_role' => 'agent', 'sanction_name' => 'Private '.$person->id, 'issuer_name' => 'Admin', 'punishment' => 'First Written']);
            SatisfactionRating::create(['request_id' => Str::uuid(), 'employee_id' => $person->id, 'employee_name' => $person->name, 'employee_role' => 'agent', 'reviewer_name' => 'Admin', 'teams' => [], ...array_fill_keys(SatisfactionRating::CATEGORIES, 4), 'average' => 4, 'comments' => 'Rating '.$person->id]);
            EmployeeFormResponse::create(['request_id' => Str::uuid(), 'employee_form_id' => $form->id, 'employee_id' => $person->id, 'employee_name' => $person->name, 'form_title' => 'Response '.$person->id, 'revision' => 1, 'teams' => [], 'answers' => [], 'points' => 1]);
        }
        $this->actingAs($own);
        foreach (['sanctions' => ['sanction_name', 'Private '], 'ratings' => ['comments', 'Rating '], 'responses' => ['form_title', 'Response ']] as $tab => [$field, $prefix]) {
            $this->get('/my-records?tab='.$tab.'&employee_id='.$other->id)->assertOk()->assertInertia(fn (Assert $p) => $p->component('personal/records')->has('records.data', 1)->where('records.data.0.'.$field, $prefix.$own->id));
        }
        Announcement::create(['title' => 'Company news', 'body' => 'For everyone', 'author_name' => 'Admin']);
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p->has('leaders', 2)->has('announcements', 1));
        $this->get('/ranking')->assertOk()->assertInertia(fn (Assert $p) => $p->has('leaders', 2));
        $empty = $this->agent();
        $this->actingAs($empty)->get('/my-records?tab=responses')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
    }

    public function test_agent_cannot_open_management_or_other_employees_coaching_training_or_notifications(): void
    {
        $own = $this->agent();
        $other = $this->agent();
        $admin = User::factory()->create(['role' => 'admin']);
        $coaching = CoachingRecord::create(['employee_id' => $other->id, 'coach_id' => $admin->id, 'coaching_date' => today(), 'type' => 'General Coaching', 'status' => 'open', 'summary' => 'Private coaching']);
        $assessment = Assessment::create(['title' => 'Private assessment', 'created_by' => $admin->id]);
        $assignment = AssessmentAssignment::create(['assessment_id' => $assessment->id, 'employee_id' => $other->id, 'assigned_by' => $admin->id, 'assigned_at' => now()]);
        $notification = AssessmentNotification::create(['recipient_id' => $other->id, 'deduplication_key' => 'private-agent-test', 'type' => 'info', 'title' => 'Private', 'message' => 'Private']);
        $this->actingAs($own);
        foreach (['/attendance', '/employees', '/teams', '/campaigns', '/campaign-schedules', '/team-assigning', '/my-team', '/requests', '/forms', '/sanctions', '/satisfaction-results', '/task-tracker', '/eod-reports', '/management/assessments', '/management/coaching', '/management/qa-dashboard', '/management/call-evaluations', '/leave-requests?scope=team', "/my-coaching/$coaching->id", "/assessments/assignments/$assignment->id/training"] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post("/my-coaching/$coaching->id/acknowledge")->assertForbidden();
        $this->post("/assessments/assignments/$assignment->id/start")->assertForbidden();
        $this->patch("/notifications/$notification->id/read")->assertForbidden();
        $this->get('/my-coaching')->assertInertia(fn (Assert $p) => $p->has('records.data', 0));
        $this->get('/assessments')->assertInertia(fn (Assert $p) => $p->has('assignments', 0));
        $this->get('/my-forms')->assertInertia(fn (Assert $p) => $p->has('forms', 0));
        $this->put('/attendance/'.$other->id.'/time', [])->assertForbidden();
    }

    public function test_time_requests_are_owned_pending_validated_and_cannot_self_approve(): void
    {
        $agent = $this->agent();
        $other = $this->agent();
        $this->schedule($agent);
        OvertimeRequest::create(['user_id' => $other->id, 'request_date' => '2026-09-28', 'request_time' => '19:00', 'reason' => 'Private', 'status' => 'approved']);
        $this->actingAs($agent);
        $payload = ['type' => 'overtime', 'request_date' => '2026-09-28', 'request_time' => '18:00', 'reason' => 'Finish assigned calls', 'user_id' => $other->id, 'status' => 'approved', 'reviewed_by' => $agent->id];
        $this->post('/my-requests', $payload)->assertSessionHasNoErrors()->assertRedirect('/my-requests?type=overtime');
        $own = OvertimeRequest::where('user_id', $agent->id)->sole();
        $this->assertSame('needs_review', $own->status);
        $this->assertNull($own->reviewed_by);
        $this->post('/my-requests', $payload)->assertSessionHasErrors('request_date');
        $this->patch('/requests/overtime/'.$own->id.'/status', ['status' => 'approved'])->assertForbidden();
        $this->get('/my-requests?user_id='.$other->id)->assertInertia(fn (Assert $p) => $p->has('requests.data', 1)->where('requests.data.0.id', $own->id));
        $this->post('/my-requests', [...$payload, 'request_date' => '2026-09-29', 'request_time' => '16:00'])->assertSessionHasErrors('request_time');
        $this->post('/my-requests', [...$payload, 'type' => 'undertime', 'request_date' => '2026-09-29', 'request_time' => '16:00'])->assertSessionHasNoErrors();
        $this->assertSame($agent->id, UndertimeRequest::sole()->user_id);
        $this->assertDatabaseCount('attendance_records', 0);
        $agent->update(['status' => 'suspended']);
        foreach (['/my-requests', '/my-records', '/my-attendance'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_agent_face_clock_and_history_are_bound_to_their_account(): void
    {
        $agent = $this->agent();
        $other = $this->agent();
        $this->schedule($agent);
        $agent->faceCredential()->create(['encrypted_descriptor' => array_fill(0, 1024, 0.1), 'model_version' => 'human-3.3.6-faceres', 'consented_at' => now(), 'enrolled_at' => now()]);
        $this->actingAs($agent)->withCredentials();
        $this->get('/my-attendance')->assertOk();
        $this->mock(AttendanceFaceVerifier::class)->shouldReceive('verify')->once()->withArgs(fn ($user) => $user->id === $agent->id)->andReturnNull();
        $id = $this->postJson('/my-attendance/challenge', ['action' => 'time_in', 'user_id' => $other->id])->assertOk()->json('id');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->postJson('/my-attendance/verify', ['challenge_id' => $id, 'frames' => array_fill(0, 3, 'data:image/jpeg;base64,YWJj'), 'user_id' => $other->id])->assertOk();
        $this->assertSame($agent->id, AttendanceRecord::sole()->user_id);
        $this->actingAs($other)->getJson('/my-attendance/records?date=2026-09-28&user_id='.$agent->id)->assertOk()->assertJsonPath('times.0.actual', null);
    }
}
