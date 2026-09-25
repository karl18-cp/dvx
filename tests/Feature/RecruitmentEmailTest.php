<?php

namespace Tests\Feature;

use App\Mail\ApplicantSubmission;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\RecruitmentEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecruitmentEmailTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['first_name' => 'Jamie', 'last_name' => 'Santos', 'email' => 'jamie@example.test', 'phone' => '09171234567', 'position' => 'Customer Service Representative', 'location' => 'Batangas', 'years_experience' => 2, 'message' => '<script>private introduction</script>'];
    }

    private function configureMail(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.username' => 'sender@example.test', 'mail.mailers.smtp.password' => 'test-only', 'recruitment.inbox' => 'divertexcorp@gmail.com']);
    }

    public function test_public_submission_emails_details_and_private_resume_to_fixed_recruitment_inbox(): void
    {
        $this->configureMail();
        Mail::fake();
        Storage::fake('local');
        $this->post('/apply', [...$this->payload(), 'resume' => UploadedFile::fake()->create('resume.pdf', 20, 'application/pdf'), 'recipient' => 'wrong@example.test', 'applicant_stage' => 'passed'])->assertRedirect();
        $application = JobApplication::sole();
        $this->assertSame('for_screening', $application->applicant_stage);
        $this->assertSame('sent', $application->recruitment_email_status);
        Mail::assertSent(ApplicantSubmission::class, function ($mail) use ($application) {
            $this->assertSame('jamie@example.test', $mail->envelope()->replyTo[0]->address);
            $this->assertCount(1, $mail->attachments());
            $html = $mail->render();
            $this->assertStringContainsString('09171234567', $html);
            $this->assertStringContainsString('jamie@example.test', $html);
            $this->assertStringNotContainsString('<script>', $html);
            $mail->assertHasAttachment(\Illuminate\Mail\Mailables\Attachment::fromStorageDisk('local', $application->resume_path)->as('resume-'.$application->id.'.pdf')->withMime('application/pdf'));

            return $mail->hasTo('divertexcorp@gmail.com');
        });
        Mail::assertSentCount(1);
    }

    public function test_missing_configuration_keeps_application_and_allows_admin_retry_after_setup(): void
    {
        config(['mail.default' => 'log']);
        Mail::fake();
        $this->post('/apply', $this->payload())->assertRedirect();
        $application = JobApplication::sole();
        $this->assertSame('not_configured', $application->recruitment_email_status);
        Mail::assertNothingSent();
        $this->configureMail();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/management/applicants/'.$application->id.'/email')->assertRedirect();
        $this->post('/management/applicants/'.$application->id.'/email')->assertRedirect();
        Mail::assertSentCount(1);
        $this->assertNotNull($application->fresh()->recruitment_emailed_at);
    }

    public function test_mail_failure_does_not_lose_application_or_resume(): void
    {
        $this->configureMail();
        Storage::fake('local');
        Mail::shouldReceive('to')->once()->with('divertexcorp@gmail.com')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->post('/apply', [...$this->payload(), 'resume' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf')])->assertRedirect();
        $application = JobApplication::sole();
        $this->assertSame('failed', $application->recruitment_email_status);
        Storage::disk('local')->assertExists($application->resume_path);
    }

    public function test_only_recruitment_managers_can_retry_download_or_review_applications(): void
    {
        $application = JobApplication::create($this->payload());
        foreach (['agent', 'team_leader'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->post('/management/applicants/'.$application->id.'/email')->assertForbidden();
            $this->get('/management/applicants/'.$application->id.'/resume')->assertForbidden();
            $this->put('/management/applicants/'.$application->id, ['scheduled_start' => '2026-10-01T09:00', 'applicant_stage' => 'passed'])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))->put('/management/applicants/'.$application->id, ['scheduled_start' => '2026-10-01T09:00', 'applicant_stage' => 'passed', 'internal_notes' => 'Private'])->assertRedirect();
        $this->assertDatabaseHas('job_applications', ['id' => $application->id, 'applicant_stage' => 'passed']);
    }
}
