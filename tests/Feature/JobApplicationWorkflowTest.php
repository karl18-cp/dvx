<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JobApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_applicant_can_submit_personal_information_and_private_resume(): void
    {
        Storage::fake('local');
        $resume = UploadedFile::fake()->create('resume.pdf', 120, 'application/pdf');

        $this->post(route('applications.store'), $this->payload(['resume' => $resume]))->assertRedirect();

        $application = JobApplication::query()->firstOrFail();
        $this->assertSame('pending', $application->screening_status);
        $this->assertSame('pending', $application->interview_status);
        $this->assertSame('for_screening', $application->applicant_stage);
        Storage::disk('local')->assertExists($application->resume_path);
    }

    public function test_resume_type_is_validated_and_public_cannot_access_management(): void
    {
        $this->post(route('applications.store'), $this->payload(['resume' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream')]))->assertSessionHasErrors('resume');
        $agent = User::factory()->create(['role' => 'agent']);
        $this->actingAs($agent)->get(route('applications.index'))->assertForbidden();
    }

    public function test_manager_can_update_screening_and_interview_outcomes(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $application = JobApplication::query()->create(collect($this->payload())->except('resume')->all());

        $this->actingAs($manager)->put(route('applications.update', $application), ['applicant_stage' => 'for_final_interview', 'internal_notes' => 'Strong initial screening.'])->assertRedirect();

        $this->assertDatabaseHas('job_applications', ['id' => $application->id, 'applicant_stage' => 'for_final_interview', 'screening_status' => 'passed', 'interview_status' => 'scheduled', 'application_status' => 'for_interview', 'reviewed_by' => $manager->id]);
    }

    private function payload(array $overrides = []): array
    {
        return [...['first_name' => 'Jamie', 'last_name' => 'Santos', 'email' => 'jamie@example.test', 'phone' => '09171234567', 'position' => 'Customer Service Representative', 'location' => 'Batangas', 'years_experience' => 2, 'message' => 'Customer service and sales experience.'], ...$overrides];
    }
}
