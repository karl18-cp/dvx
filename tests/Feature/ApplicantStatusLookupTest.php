<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantStatusLookupTest extends TestCase
{
    use RefreshDatabase;

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
