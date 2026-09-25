<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantStatusLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_is_public_and_does_not_preload_applicant_records(): void
    {
        $this->withoutVite()->get('/applicant-portal')->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('applicant-portal')->missing('application')->missing('applications'));
    }

    public function test_portal_shows_admin_updates_and_keeps_internal_notes_and_resume_private(): void
    {
        $application = JobApplication::create([
            'first_name' => 'Ana', 'last_name' => 'Santos', 'email' => 'ana@example.test',
            'phone' => '09171234567', 'phone_normalized' => '9171234567',
            'position' => 'Customer Service Representative', 'years_experience' => 1,
            'resume_path' => 'private/resume.pdf',
        ]);
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put(route('applications.update', $application), [
            'scheduled_start' => '2026-10-01T09:00', 'applicant_stage' => 'for_final_interview', 'applicant_update' => 'Please contact recruitment to arrange your interview.', 'internal_notes' => 'Internal assessment only.',
        ])->assertRedirect();
        $this->app['auth']->forgetGuards();
        $question = $this->getJson('/application-status/challenge')->assertOk()->json('question');
        preg_match('/(\d+) \+ (\d+)/', $question, $numbers);
        $response = $this->postJson('/application-status', ['phone' => '09171234567', 'email' => 'ana@example.test', 'challenge_answer' => (int) $numbers[1] + (int) $numbers[2]])
            ->assertOk()->assertJsonPath('application.applicant_stage', 'for_final_interview')
            ->assertJsonPath('application.applicant_update', 'Please contact recruitment to arrange your interview.')
            ->assertJsonPath('application.schedule_label', 'Final interview')
            ->assertJsonPath('application.scheduled_start_at', '2026-10-01T01:00:00+00:00')
            ->assertJsonMissingPath('application.internal_notes')->assertJsonMissingPath('application.resume_path');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('Internal assessment only.', $response->getContent());
    }

    public function test_wrong_contact_details_and_reused_challenges_do_not_reveal_an_application(): void
    {
        JobApplication::create(['first_name' => 'Ana', 'last_name' => 'Santos', 'email' => 'ana@example.test', 'phone' => '09171234567', 'phone_normalized' => '9171234567', 'position' => 'Other', 'years_experience' => 0]);
        $question = $this->getJson('/application-status/challenge')->json('question');
        preg_match('/(\d+) \+ (\d+)/', $question, $numbers);
        $data = ['phone' => '09171234567', 'email' => 'someone-else@example.test', 'challenge_answer' => (int) $numbers[1] + (int) $numbers[2]];
        $this->postJson('/application-status', $data)->assertNotFound()->assertJsonMissingPath('application');
        $this->postJson('/application-status', [...$data, 'email' => 'ana@example.test'])->assertUnprocessable()->assertJsonMissingPath('application');
    }

    public function test_applicant_can_view_only_public_updates_after_identity_and_human_checks(): void
    {
        JobApplication::query()->create([
            'first_name' => 'Ana', 'last_name' => 'Santos', 'email' => 'ana@example.test',
            'phone' => '+63 917 123 4567', 'phone_normalized' => '9171234567',
            'position' => 'Quality Analyst', 'years_experience' => 2,
            'screening_status' => 'passed', 'interview_status' => 'scheduled',
            'application_status' => 'for_interview', 'applicant_stage' => 'for_final_interview', 'applicant_update' => 'Your interview is ready to schedule.',
            'internal_notes' => 'Never expose this note.',
        ]);

        $challenge = $this->getJson('/application-status/challenge')->assertOk()->json('question');
        preg_match('/(\d+) \+ (\d+)/', $challenge, $numbers);

        $response = $this->postJson('/application-status', [
            'phone' => '0917-123-4567', 'email' => 'ANA@example.test',
            'challenge_answer' => (int) $numbers[1] + (int) $numbers[2],
        ])->assertOk()
            ->assertJsonPath('application.applicant_stage', 'for_final_interview')
            ->assertJsonPath('application.applicant_update', 'Your interview is ready to schedule.');

        $this->assertStringNotContainsString('Never expose this note.', $response->getContent());
    }

    public function test_lookup_rejects_wrong_identity_or_human_answer(): void
    {
        JobApplication::query()->create([
            'first_name' => 'Ana', 'last_name' => 'Santos', 'email' => 'ana@example.test',
            'phone' => '09171234567', 'phone_normalized' => '9171234567',
            'position' => 'Quality Analyst', 'years_experience' => 2,
        ]);

        $this->getJson('/application-status/challenge')->assertOk();
        $this->postJson('/application-status', [
            'phone' => '09171234567', 'email' => 'wrong@example.test', 'challenge_answer' => 999,
        ])->assertUnprocessable()->assertJsonPath('message', 'Human verification failed. Please try a new question.');
    }
}
