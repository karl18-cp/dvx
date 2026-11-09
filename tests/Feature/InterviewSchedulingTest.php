<?php

namespace Tests\Feature;

use App\Mail\InterviewCalendarInvitation;
use App\Models\InterviewAppointment;
use App\Models\InterviewAppointmentEvent;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\InterviewInvitationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InterviewSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.test',
            'mail.mailers.smtp.username' => 'test', 'mail.mailers.smtp.password' => 'secret',
            'mail.from.address' => 'notifications@divertexcorp.com',
        ]);
        Mail::fake();
    }

    public function test_admin_schedules_interview_and_sends_calendar_invitations(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $interviewer = User::factory()->create(['role' => 'team_leader', 'status' => 'active', 'email' => 'leader@example.test']);
        $application = $this->application();
        $start = CarbonImmutable::now('Asia/Manila')->addDays(2)->setTime(10, 0)->format('Y-m-d\TH:i');

        $this->actingAs($admin)->post('/management/interview-calendar', [
            'job_application_id' => $application->id, 'interviewer_id' => $interviewer->id,
            'interview_type' => 'screening', 'starts_at' => $start, 'duration' => 60,
            'location' => 'Recruitment room', 'meeting_url' => 'https://meet.example.test/interview', 'notes' => 'Initial screening.',
        ])->assertSessionHasNoErrors();

        $appointment = InterviewAppointment::sole();
        $this->assertSame('scheduled', $appointment->status);
        $this->assertSame('sent', $appointment->applicant_invite_status);
        $this->assertSame('sent', $appointment->interviewer_invite_status);
        $this->assertSame($appointment->starts_at->getTimestamp(), $application->fresh()->scheduled_start_at->getTimestamp());
        $this->assertSame('for_screening', $application->fresh()->applicant_stage);
        $this->assertDatabaseHas('interview_appointment_events', ['interview_appointment_id' => $appointment->id, 'event_type' => 'scheduled']);
        $this->assertDatabaseHas('activity_logs', ['category' => 'recruitment', 'action' => 'Interview scheduled']);
        $this->assertDatabaseHas('assessment_notifications', ['recipient_id' => $interviewer->id, 'type' => 'interview']);
        Mail::assertSent(InterviewCalendarInvitation::class, 2);

        $calendar = app(InterviewInvitationService::class)->calendar($appointment);
        $this->assertStringContainsString('UID:'.$appointment->calendar_uid.'@divertexcorp.com', $calendar);
        $this->assertStringContainsString('METHOD:REQUEST', $calendar);
    }

    public function test_availability_prevents_conflicts_and_reschedule_tracks_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $interviewer = User::factory()->create(['role' => 'qa_admin', 'status' => 'active']);
        $first = $this->application();
        $second = $this->application('alex@example.test');
        $day = CarbonImmutable::now('Asia/Manila')->addDays(3)->startOfDay();
        $start = $day->setTime(10, 0)->format('Y-m-d\TH:i');

        $this->actingAs($admin)->post('/management/interview-calendar', [
            'job_application_id' => $first->id, 'interviewer_id' => $interviewer->id,
            'interview_type' => 'screening', 'starts_at' => $start, 'duration' => 60,
        ])->assertSessionHasNoErrors();
        $appointment = InterviewAppointment::sole();

        $slots = $this->getJson('/management/interview-calendar/availability?'.http_build_query([
            'interviewer_id' => $interviewer->id, 'date' => $day->format('Y-m-d'), 'duration' => 60,
        ]))->assertOk()->json('slots');
        $this->assertNotContains($start, array_column($slots, 'value'));

        $this->post('/management/interview-calendar', [
            'job_application_id' => $second->id, 'interviewer_id' => $interviewer->id,
            'interview_type' => 'screening', 'starts_at' => $day->setTime(10, 30)->format('Y-m-d\TH:i'), 'duration' => 60,
        ])->assertSessionHasErrors('starts_at');

        $newStart = $day->setTime(12, 0)->format('Y-m-d\TH:i');
        $this->post("/management/interview-calendar/{$appointment->id}/reschedule", [
            'job_application_id' => $first->id, 'interviewer_id' => $interviewer->id,
            'interview_type' => 'final_interview', 'starts_at' => $newStart, 'duration' => 60, 'notes' => 'Applicant requested a new time.',
        ])->assertSessionHasNoErrors();

        $appointment->refresh();
        $this->assertSame('rescheduled', $appointment->status);
        $this->assertSame(1, $appointment->calendar_sequence);
        $this->assertSame(1, $appointment->reschedule_count);
        $this->assertSame(2, InterviewAppointmentEvent::where('interview_appointment_id', $appointment->id)->count());
        $this->assertSame('for_final_interview', $first->fresh()->applicant_stage);
        Mail::assertSent(InterviewCalendarInvitation::class, 4);
    }

    public function test_no_show_is_tracked_and_employee_cannot_manage_interviews(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $interviewer = User::factory()->create(['role' => 'team_leader', 'status' => 'active']);
        $application = $this->application();
        $start = CarbonImmutable::now('Asia/Manila')->addDay()->setTime(14, 0)->format('Y-m-d\TH:i');
        $this->actingAs($admin)->post('/management/interview-calendar', [
            'job_application_id' => $application->id, 'interviewer_id' => $interviewer->id,
            'interview_type' => 'screening', 'starts_at' => $start, 'duration' => 30,
        ]);
        $appointment = InterviewAppointment::sole();

        $this->patch("/management/interview-calendar/{$appointment->id}/status", ['status' => 'no_show', 'notes' => 'Applicant did not join.'])->assertSessionHasNoErrors();
        $this->assertSame('no_show', $appointment->fresh()->status);
        $this->assertNull($application->fresh()->scheduled_start_at);
        $this->assertDatabaseHas('interview_appointment_events', ['event_type' => 'no_show', 'notes' => 'Applicant did not join.']);

        $this->actingAs($agent)->get('/management/interview-calendar')->assertForbidden();
        $this->post('/management/interview-calendar', [])->assertForbidden();
    }

    private function application(string $email = 'applicant@example.test'): JobApplication
    {
        return JobApplication::create([
            'first_name' => 'Jamie', 'last_name' => 'Reyes', 'email' => $email,
            'phone' => '09170000000', 'phone_normalized' => '9170000000',
            'position' => 'Customer Service Representative', 'years_experience' => 1,
            'applicant_stage' => 'for_screening',
        ]);
    }
}
