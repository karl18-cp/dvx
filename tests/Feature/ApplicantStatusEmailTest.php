<?php

namespace Tests\Feature;

use App\Mail\ApplicantStatusUpdated;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApplicantStatusEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_interview_and_training_stages_require_a_start_datetime(): void
    {
        $application = $this->application();
        Mail::fake();
        foreach (['for_screening', 'for_final_interview', 'passed'] as $stage) {
            $this->put(route('applications.update', $application), ['applicant_stage' => $stage])->assertSessionHasErrors('scheduled_start');
            $this->put(route('applications.update', $application), ['applicant_stage' => $stage, 'scheduled_start' => '2026-02-30T09:00'])->assertSessionHasErrors('scheduled_start');
        }
        Mail::assertNothingSent();
        $this->assertSame('for_screening', $application->fresh()->applicant_stage);
    }

    public function test_schedule_is_converted_from_manila_and_rescheduling_notifies_applicant(): void
    {
        $application = $this->application();
        Mail::fake();
        $url = route('applications.update', $application);
        $this->put($url, ['applicant_stage' => 'passed', 'scheduled_start' => '2026-10-01T09:30'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('2026-10-01 01:30:00', $application->fresh()->scheduled_start_at->format('Y-m-d H:i:s'));
        Mail::assertSent(ApplicantStatusUpdated::class, fn ($mail) => $mail->scheduleLabel === 'Training' && str_contains($mail->render(), 'October 1, 2026, 9:30 AM'));
        $this->put($url, ['applicant_stage' => 'passed', 'scheduled_start' => '2026-10-02T10:00'])->assertRedirect();
        Mail::assertSentCount(2);
        $this->put($url, ['applicant_stage' => 'failed'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($application->fresh()->scheduled_start_at);
    }

    private function application(): JobApplication
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.username' => 'sender@example.test', 'mail.mailers.smtp.password' => 'test-only', 'app.url' => 'https://dvx.test']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        return JobApplication::create(['first_name' => 'Ana', 'last_name' => 'Santos', 'email' => 'ana@example.test', 'phone' => '09171234567', 'position' => 'Other', 'years_experience' => 0]);
    }

    public function test_all_stage_changes_notify_only_the_applicant_with_public_information(): void
    {
        $application = $this->application();
        Mail::fake();
        foreach (['for_final_interview', 'passed', 'failed', 'for_screening'] as $stage) {
            $this->put(route('applications.update', $application), ['scheduled_start' => '2026-10-01T09:00', 'applicant_stage' => $stage, 'applicant_update' => 'Public instructions', 'internal_notes' => 'Secret hiring notes'])->assertRedirect();
        }
        Mail::assertSentCount(4);
        Mail::assertSent(ApplicantStatusUpdated::class, function ($mail) {
            $this->assertTrue($mail->hasTo('ana@example.test'));
            $this->assertFalse($mail->hasTo('divertexcorp@gmail.com'));
            $this->assertStringContainsString('Public instructions', $mail->render());
            $this->assertStringNotContainsString('Secret hiring notes', $mail->render());
            $this->assertSame('https://dvx.test/applicant-portal', $mail->portalUrl);

            return true;
        });
        $this->assertFalse((bool) $application->fresh()->applicant_email_pending);
        $this->assertNotNull($application->fresh()->applicant_emailed_at);
    }

    public function test_internal_notes_and_duplicate_saves_do_not_send_but_public_messages_do(): void
    {
        $application = $this->application();
        Mail::fake();
        $url = route('applications.update', $application);
        $application->forceFill(['scheduled_start_at' => '2026-10-01 01:00:00'])->save();
        $this->put($url, ['scheduled_start' => '2026-10-01T09:00', 'applicant_stage' => 'for_screening', 'internal_notes' => 'Private only'])->assertRedirect();
        Mail::assertNothingSent();
        $payload = ['scheduled_start' => '2026-10-01T09:00', 'applicant_stage' => 'for_screening', 'applicant_update' => 'Please call recruitment.'];
        $this->put($url, $payload)->assertRedirect();
        $this->put($url, $payload)->assertRedirect();
        Mail::assertSentCount(1);
    }

    public function test_mail_failure_preserves_review_and_same_save_retries_once(): void
    {
        $application = $this->application();
        Mail::shouldReceive('to')->once()->with('ana@example.test')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $payload = ['scheduled_start' => '2026-10-01T09:00', 'applicant_stage' => 'passed'];
        $url = route('applications.update', $application);
        $this->put($url, $payload)->assertRedirect()->assertSessionHas('status', fn ($message) => str_contains($message, 'could not be sent'));
        $this->assertDatabaseHas('job_applications', ['id' => $application->id, 'applicant_stage' => 'passed', 'applicant_email_pending' => true]);
        Mail::swap(new \Illuminate\Mail\MailManager($this->app));
        Mail::fake();
        $this->put($url, $payload)->assertRedirect();
        $this->put($url, $payload)->assertRedirect();
        Mail::assertSentCount(1);
        $this->assertFalse((bool) $application->fresh()->applicant_email_pending);
    }

    public function test_unauthorized_and_invalid_changes_send_no_email(): void
    {
        $application = $this->application();
        Mail::fake();
        $this->put(route('applications.update', $application), ['scheduled_start' => '2026-10-01T09:00', 'applicant_stage' => 'invalid'])->assertSessionHasErrors('applicant_stage');
        $this->actingAs(User::factory()->create(['role' => 'agent']))->put(route('applications.update', $application), ['scheduled_start' => '2026-10-01T09:00', 'applicant_stage' => 'passed'])->assertForbidden();
        Mail::assertNothingSent();
    }
}
