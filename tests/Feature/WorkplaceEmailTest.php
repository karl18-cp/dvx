<?php

namespace Tests\Feature;

use App\Jobs\SendWorkplaceEmail;
use App\Mail\WorkplaceUpdate;
use App\Models\Announcement;
use App\Models\Campaign;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\EmployeeForm;
use App\Models\EmployeeFormResponse;
use App\Models\EmployeeSanction;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\Team;
use App\Models\TeamLeaderAssignment;
use App\Models\TeamMember;
use App\Models\TrackerTask;
use App\Models\User;
use App\Models\WorkplaceEmailDelivery;
use App\Services\WorkplaceEmailService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkplaceEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-28 09:00', 'Asia/Manila'));
        config(['workplace-email.enabled' => true, 'workplace-email.start_at' => now()->subDay()->utc()->toIso8601String(), 'app.url' => 'https://divertexcorp.com']);
        Queue::fake();
        Mail::fake();
    }

    private function person(string $role = 'agent'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active', 'email' => Str::random(12).'@divertexcorp.com']);
    }

    private function sendAll(): void
    {
        foreach (WorkplaceEmailDelivery::pluck('id') as $id) {
            app(WorkplaceEmailService::class)->deliver($id);
        }
    }

    public function test_sanctions_and_approval_emails_are_scoped_and_deduplicated(): void
    {
        $admin = $this->person('admin');
        $agent = $this->person();
        $other = $this->person();
        EmployeeSanction::create(['request_id' => Str::uuid(), 'employee_id' => $agent->id, 'issued_by' => $admin->id, 'employee_name' => $agent->name, 'employee_role' => 'agent', 'issuer_name' => $admin->name, 'sanction_name' => 'Tardiness', 'punishment' => 'First Written']);
        $request = OvertimeRequest::create(['user_id' => $agent->id, 'request_date' => now()->toDateString(), 'request_time' => '18:00', 'reason' => 'Private overtime reason']);
        $service = app(WorkplaceEmailService::class);
        $service->collect();
        $this->assertDatabaseCount('workplace_email_deliveries', 3);
        Queue::assertPushed(SendWorkplaceEmail::class, 3);
        $this->sendAll();
        $service->collect();
        $this->sendAll();
        Mail::assertSent(WorkplaceUpdate::class, 3);
        Mail::assertNotSent(WorkplaceUpdate::class, fn ($mail) => $mail->hasTo($other->email));
        $request->update(['status' => 'approved']);
        $service->collect();
        $this->sendAll();
        Mail::assertSent(WorkplaceUpdate::class, fn ($mail) => $mail->hasTo($agent->email) && str_contains($mail->heading, 'approved'));
        Mail::assertSent(WorkplaceUpdate::class, 4);
    }

    public function test_no_historical_flood_and_no_mail_when_disabled(): void
    {
        $person = $this->person();
        Announcement::create(['title' => 'Old news', 'body' => 'Before activation', 'author_name' => 'Admin'])->forceFill(['created_at' => now()->subWeek(), 'updated_at' => now()->subWeek()])->save();
        $service = app(WorkplaceEmailService::class);
        $this->assertSame(0, $service->collect());
        Announcement::create(['title' => 'Fresh news', 'body' => 'Today', 'author_name' => 'Admin']);
        config(['workplace-email.enabled' => false]);
        $this->assertSame(0, $service->collect());
        config(['workplace-email.enabled' => true]);
        $this->assertSame(1, $service->collect());
        $this->sendAll();
        Mail::assertSent(WorkplaceUpdate::class, 1);
    }

    public function test_deleted_alerts_deactivated_users_and_placeholder_emails_are_skipped(): void
    {
        $active = $this->person();
        $inactive = $this->person();
        $placeholder = $this->person();
        $placeholder->update(['email' => 'dvx001@divertex.local']);
        $announcement = Announcement::create(['title' => 'News', 'body' => 'Body', 'author_name' => 'Admin']);
        app(WorkplaceEmailService::class)->collect();
        $inactive->update(['status' => 'inactive']);
        app(WorkplaceEmailService::class)->deliver(WorkplaceEmailDelivery::where('recipient_id', $inactive->id)->value('id'));
        app(WorkplaceEmailService::class)->deliver(WorkplaceEmailDelivery::where('recipient_id', $placeholder->id)->value('id'));
        $announcement->delete();
        $this->sendAll();
        Mail::assertNothingSent();
        $this->assertSame(3, WorkplaceEmailDelivery::where('status', 'skipped')->count());
    }

    public function test_role_change_removes_private_admin_alert_before_delivery(): void
    {
        $admin = $this->person('admin');
        $agent = $this->person();
        OvertimeRequest::create(['user_id' => $agent->id, 'request_date' => now()->toDateString(), 'request_time' => '18:00', 'reason' => 'Private']);
        app(WorkplaceEmailService::class)->collect();
        $admin->update(['role' => 'agent']);
        $this->sendAll();
        Mail::assertNothingSent();
        $this->assertDatabaseHas('workplace_email_deliveries', ['status' => 'skipped']);
    }

    public function test_smtp_failure_can_retry_without_losing_delivery_or_resending_success(): void
    {
        $user = $this->person();
        Announcement::create(['title' => 'News', 'body' => 'Body', 'author_name' => 'Admin']);
        $service = app(WorkplaceEmailService::class);
        $service->collect();
        $id = WorkplaceEmailDelivery::sole()->id;
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('sensitive transport detail'));
        try {
            $service->deliver($id);
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('sensitive', $e->getMessage());
        }
        $this->assertDatabaseHas('workplace_email_deliveries', ['id' => $id, 'status' => 'failed', 'attempts' => 1]);
        Mail::swap(new MailManager($this->app));
        Mail::fake();
        $service->deliver($id);
        $service->deliver($id);
        Mail::assertSent(WorkplaceUpdate::class, 1);
        $this->assertDatabaseHas('workplace_email_deliveries', ['id' => $id, 'status' => 'sent', 'attempts' => 2]);
    }

    public function test_unread_chat_is_batched_and_does_not_include_message_contents(): void
    {
        $agent = $this->person();
        $peer = $this->person();
        $other = $this->person();
        $room = ChatConversation::create(['conversation_key' => 'direct:test', 'type' => 'direct', 'first_user_id' => $agent->id, 'second_user_id' => $peer->id]);
        $message = ChatMessage::create(['request_id' => Str::uuid(), 'conversation_id' => $room->id, 'sender_id' => $peer->id, 'sender_name' => $peer->name, 'body' => 'Sensitive message text']);
        $message->forceFill(['created_at' => now()->subMinutes(10)])->save();
        $service = app(WorkplaceEmailService::class);
        $service->collectChat();
        $service->collectChat();
        $this->sendAll();
        Mail::assertSent(WorkplaceUpdate::class, 1);
        Mail::assertSent(WorkplaceUpdate::class, fn ($mail) => $mail->hasTo($agent->email) && ! str_contains($mail->body, 'Sensitive'));
        $this->assertDatabaseCount('workplace_email_deliveries', 1);
    }

    public function test_weekly_rankings_use_last_week_and_team_scope(): void
    {
        $admin = $this->person('admin');
        $leader = $this->person('team_leader');
        $agent = $this->person();
        $other = $this->person();
        $campaign = Campaign::create(['name' => 'Campaign', 'abbreviation' => 'CMP']);
        $team = Team::create(['name' => 'Alpha', 'campaign_id' => $campaign->id]);
        TeamLeaderAssignment::create(['team_id' => $team->id, 'user_id' => $leader->id]);
        TeamMember::create(['team_id' => $team->id, 'user_id' => $agent->id]);
        $form = EmployeeForm::create(['title' => 'Sales', 'fields' => [], 'status' => 'active', 'ranking_enabled' => true]);
        foreach ([[$agent, 1, '2026-09-22 08:00'], [$other, 2, '2026-09-23 08:00'], [$agent, 3, '2026-09-28 00:30']] as [$person, $points, $date]) {
            EmployeeFormResponse::create(['request_id' => Str::uuid(), 'employee_form_id' => $form->id, 'employee_id' => $person->id, 'employee_name' => $person->name, 'form_title' => 'Sales', 'revision' => 1, 'teams' => [], 'answers' => [], 'points' => $points])->forceFill(['created_at' => CarbonImmutable::parse($date, 'Asia/Manila')->utc()])->save();
        }
        $service = app(WorkplaceEmailService::class);
        $service->collectWeekly();
        $service->collectWeekly();
        $this->sendAll();
        Mail::assertSent(WorkplaceUpdate::class, 4);
        Mail::assertSent(WorkplaceUpdate::class, fn ($mail) => $mail->hasTo($agent->email) && str_contains($mail->body, 'Points earned this week: 1') && str_contains($mail->body, 'Your position: #2'));
        Mail::assertSent(WorkplaceUpdate::class, fn ($mail) => $mail->hasTo($leader->email) && str_contains($mail->body, $agent->name) && ! str_contains($mail->body, $other->name));
    }

    public function test_email_body_escapes_untrusted_content(): void
    {
        $html = (new WorkplaceUpdate('Employee', 'An update', '<script>alert(1)</script>'))->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('https://divertexcorp.com/login', $html);
    }

    public function test_agent_leave_goes_to_the_correct_leader_then_admin_then_employee(): void
    {
        $admin = $this->person('admin');
        $leader = $this->person('team_leader');
        $outsider = $this->person('team_leader');
        $agent = $this->person();
        $campaign = Campaign::create(['name' => 'Campaign', 'abbreviation' => 'CMP']);
        $team = Team::create(['name' => 'Alpha', 'campaign_id' => $campaign->id]);
        TeamLeaderAssignment::create(['team_id' => $team->id, 'user_id' => $leader->id]);
        TeamMember::create(['team_id' => $team->id, 'user_id' => $agent->id]);
        $leave = LeaveRequest::create(['user_id' => $agent->id, 'start_date' => '2026-09-29', 'end_date' => '2026-09-29', 'number_of_days' => 1, 'leave_type' => 'Vacation', 'reason' => 'Private leave', 'requires_leader_approval' => true, 'leader_status' => 'pending']);
        $leave->forceFill(['requires_leader_approval' => true, 'leader_status' => 'pending'])->save();
        $service = app(WorkplaceEmailService::class);
        $service->collect();
        $this->sendAll();
        Mail::assertSent(WorkplaceUpdate::class, 1);
        Mail::assertSent(WorkplaceUpdate::class, fn ($mail) => $mail->hasTo($leader->email));
        $leave->forceFill(['leader_status' => 'approved'])->save();
        $service->collect();
        $this->sendAll();
        Mail::assertSent(WorkplaceUpdate::class, fn ($mail) => $mail->hasTo($admin->email) && str_contains($mail->heading, 'awaiting review'));
        $leave->update(['status' => 'approved']);
        $service->collect();
        $this->sendAll();
        Mail::assertSent(WorkplaceUpdate::class, fn ($mail) => $mail->hasTo($agent->email) && $mail->heading === 'Leave request approved');
        Mail::assertNotSent(WorkplaceUpdate::class, fn ($mail) => $mail->hasTo($outsider->email));
    }

    public function test_task_updates_are_for_assignee_and_eligible_reviewers(): void
    {
        $admin = $this->person('admin');
        $leader = $this->person('team_leader');
        $outsider = $this->person('team_leader');
        $task = TrackerTask::create(['request_id' => Str::uuid(), 'created_by' => $admin->id, 'creator_name' => $admin->name, 'title' => 'Private task', 'checklist' => []]);
        $assignment = $task->assignments()->create(['user_id' => $leader->id, 'assignee_name' => $leader->name, 'assignee_role' => 'team_leader', 'completed_items' => []]);
        $service = app(WorkplaceEmailService::class);
        $service->collect();
        $this->sendAll();
        Mail::assertSent(WorkplaceUpdate::class, 1);
        $assignment->forceFill(['status' => 'pending_review', 'submitted_at' => now(), 'version' => 2])->save();
        $service->collect();
        $this->sendAll();
        Mail::assertSent(WorkplaceUpdate::class, fn ($mail) => $mail->hasTo($admin->email) && $mail->heading === 'Task awaiting your review');
        $assignment->forceFill(['status' => 'approved_done', 'reviewed_at' => now(), 'version' => 3])->save();
        $service->collect();
        $this->sendAll();
        Mail::assertSent(WorkplaceUpdate::class, fn ($mail) => $mail->hasTo($leader->email) && $mail->heading === 'Task completion approved');
        Mail::assertNotSent(WorkplaceUpdate::class, fn ($mail) => $mail->hasTo($outsider->email));
    }

    public function test_chat_read_before_delivery_does_not_send_a_stale_reminder(): void
    {
        $agent = $this->person();
        $peer = $this->person();
        $room = ChatConversation::create(['conversation_key' => 'direct:test', 'type' => 'direct', 'first_user_id' => $agent->id, 'second_user_id' => $peer->id]);
        $message = ChatMessage::create(['request_id' => Str::uuid(), 'conversation_id' => $room->id, 'sender_id' => $peer->id, 'sender_name' => $peer->name, 'body' => 'Unread']);
        $message->forceFill(['created_at' => now()->subMinutes(10)])->save();
        app(WorkplaceEmailService::class)->collectChat();
        DB::table('chat_reads')->insert(['conversation_id' => $room->id, 'user_id' => $agent->id, 'last_read_message_id' => $message->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->sendAll();
        Mail::assertNothingSent();
        $this->assertDatabaseHas('workplace_email_deliveries', ['status' => 'skipped']);
    }
}
