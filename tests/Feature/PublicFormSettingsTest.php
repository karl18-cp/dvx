<?php

namespace Tests\Feature;

use App\Mail\ApplicantSubmission;
use App\Mail\BusinessInquiryReceived;
use App\Models\BusinessInquiry;
use App\Models\JobApplication;
use App\Models\PublicFormSetting;
use App\Models\User;
use App\Services\PublicFormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicFormSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function question(): array
    {
        return ['id' => 'custom_budget', 'label' => 'Team budget', 'type' => 'select', 'required' => true, 'visible' => true, 'placeholder' => '', 'options' => ['Small team', 'Large team']];
    }

    private function application(): array
    {
        return ['first_name' => 'Test', 'last_name' => 'Applicant', 'email' => 'applicant@example.test', 'phone' => '09171234567', 'position' => 'New position', 'years_experience' => 1];
    }

    public function test_admin_can_customize_form_and_public_page_receives_it(): void
    {
        $data = app(PublicFormService::class)->defaults('application');
        $data['fields'][4]['options'] = ['New position'];
        $data['fields'][] = $this->question();
        $this->actingAs($this->admin())->put('/public-form-settings/application', $data)->assertSessionHasNoErrors();
        $this->get('/careers')->assertInertia(fn (Assert $page) => $page->where('publicForms.application.fields.4.options', ['New position'])->where('publicForms.application.fields.9.label', 'Team budget'));
    }

    public function test_application_uses_saved_rules_and_preserves_answer_labels(): void
    {
        Mail::fake();
        $data = app(PublicFormService::class)->defaults('application');
        $data['fields'][4]['options'] = ['New position'];
        $data['fields'][] = [...$this->question(), 'custom' => true, 'locked' => false];
        PublicFormSetting::create(['kind' => 'application', 'definition' => $data]);
        $this->post('/apply', $this->application())->assertSessionHasErrors('custom_fields.custom_budget');
        $this->post('/apply', [...$this->application(), 'custom_fields' => ['custom_budget' => 'Invalid']])->assertSessionHasErrors('custom_fields.custom_budget');
        $this->post('/apply', [...$this->application(), 'custom_fields' => ['custom_budget' => 'Small team', 'forged' => 'ignore']])->assertSessionHasNoErrors();
        $application = JobApplication::firstOrFail();
        $this->assertCount(1, $application->custom_answers);
        $this->assertSame('Team budget', $application->custom_answers[0]['label']);
        PublicFormSetting::where('kind', 'application')->delete();
        $this->assertSame('Team budget', $application->fresh()->custom_answers[0]['label']);
        $html = (new ApplicantSubmission($application))->render();
        $this->assertStringContainsString('Small team', $html);
    }

    public function test_business_rules_and_answers_are_used_and_emailed(): void
    {
        Mail::fake();
        $data = app(PublicFormService::class)->defaults('business');
        $data['fields'][4]['options'] = ['Custom service'];
        $data['fields'][] = [...$this->question(), 'custom' => true, 'locked' => false];
        PublicFormSetting::create(['kind' => 'business', 'definition' => $data]);
        $payload = ['submission_id' => (string) Str::uuid(), 'name' => 'Client', 'company' => 'Company', 'email' => 'client@example.test', 'service' => 'Custom service', 'message' => 'Hello', 'meeting_time' => now()->addDays(3)->format('Y-m-d\TH:i'), 'timezone' => 'UTC'];
        $this->post('/business-inquiries', $payload)->assertSessionHasErrors('custom_fields.custom_budget');
        $this->post('/business-inquiries', [...$payload, 'custom_fields' => ['custom_budget' => 'Large team']])->assertSessionHasNoErrors();
        $inquiry = BusinessInquiry::firstOrFail();
        $this->assertSame('Large team', $inquiry->custom_answers[0]['value']);
        $this->assertStringContainsString('Large team', (new BusinessInquiryReceived($inquiry))->render());
    }

    public function test_protected_fields_and_other_roles_cannot_bypass_settings(): void
    {
        $data = app(PublicFormService::class)->defaults('application');
        foreach (['manager', 'qa_admin', 'team_leader', 'agent', 'trainee'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active']));
            $this->get('/public-form-settings')->assertForbidden();
            $this->put('/public-form-settings/application', $data)->assertForbidden();
        }
        $this->actingAs($this->admin());
        $data['fields'][0]['visible'] = false;
        $this->put('/public-form-settings/application', $data)->assertSessionHasErrors('fields.0.label');
        $data = app(PublicFormService::class)->defaults('application');
        array_shift($data['fields']);
        $this->put('/public-form-settings/application', $data)->assertSessionHasErrors('fields');
        $this->assertDatabaseCount('public_form_settings', 0);
    }

    public function test_hidden_optional_fields_are_ignored_and_resume_can_be_required(): void
    {
        Mail::fake();
        $data = app(PublicFormService::class)->defaults('application');
        $data['fields'][5]['visible'] = false;
        $data['fields'][7]['required'] = true;
        PublicFormSetting::create(['kind' => 'application', 'definition' => $data]);
        $payload = [...$this->application(), 'position' => 'Other', 'location' => 'Hidden data'];
        $this->post('/apply', $payload)->assertSessionHasErrors('resume');
        $data['fields'][7]['required'] = false;
        PublicFormSetting::where('kind', 'application')->update(['definition' => $data]);
        $this->post('/apply', $payload)->assertSessionHasNoErrors();
        $this->assertNull(JobApplication::firstOrFail()->location);
    }
}
