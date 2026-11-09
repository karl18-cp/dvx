<?php

namespace Tests\Feature;

use App\Models\AssessmentNotification;
use App\Models\ComplianceDocument;
use App\Models\EmployeeContract;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\DocumentExpirationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_track_employee_and_applicant_expiration_requirements(): void
    {
        $this->withoutVite();
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $applicant = JobApplication::create([
            'first_name' => 'Jamie', 'last_name' => 'Reyes', 'email' => 'jamie@example.test',
            'phone' => '09170000000', 'phone_normalized' => '639170000000', 'position' => 'Agent',
        ]);

        $this->actingAs($admin)->post('/document-expirations', [
            'subject_type' => 'employee', 'user_id' => $employee->id, 'document_type' => 'government_id',
            'title' => 'Passport', 'issuer' => 'DFA', 'document_number' => 'P123', 'issued_at' => now()->subYear()->toDateString(),
            'expires_at' => now()->addDays(20)->toDateString(), 'remind_days_before' => 30, 'status' => 'active',
            'notes' => 'Renew before travel.', 'document' => UploadedFile::fake()->create('passport.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $document = ComplianceDocument::sole();
        Storage::disk('local')->assertExists($document->file_path);
        $this->get('/document-expirations')->assertOk()->assertInertia(fn ($page) => $page
            ->component('document-expirations')->has('records', 1)->where('records.0.subject_name', $employee->name));

        $this->post('/document-expirations', [
            'subject_type' => 'applicant', 'job_application_id' => $applicant->id,
            'document_type' => 'pre_employment_requirement', 'title' => 'NBI Clearance',
            'expires_at' => now()->addDays(60)->toDateString(), 'remind_days_before' => 30, 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('compliance_documents', ['job_application_id' => $applicant->id, 'user_id' => null]);
        $this->assertDatabaseHas('activity_logs', ['category' => 'documents', 'action' => 'Compliance document added']);
    }

    public function test_scan_notifies_authorized_administrators_once_for_documents_and_contracts(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $qa = User::factory()->create(['role' => 'qa_admin', 'status' => 'active']);
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        ComplianceDocument::create([
            'subject_type' => 'employee', 'user_id' => $agent->id, 'document_type' => 'certification',
            'title' => 'Data Privacy Certification', 'expires_at' => now()->addDays(7)->toDateString(),
            'remind_days_before' => 30, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        Storage::disk('local')->put('employee-contracts/test.pdf', 'contract');
        EmployeeContract::create([
            'request_key' => Str::uuid(), 'version_group' => Str::uuid(), 'version' => 1, 'user_id' => $agent->id,
            'contract_type' => 'employment', 'title' => 'Employment Agreement', 'effective_date' => now()->subYear()->toDateString(),
            'end_date' => now()->addDays(15)->toDateString(), 'status' => 'active', 'signing_status' => 'signed',
            'file_disk' => 'local', 'file_path' => 'employee-contracts/test.pdf', 'original_filename' => 'contract.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 8, 'created_by' => $admin->id,
        ]);

        $first = app(DocumentExpirationService::class)->scan();
        $second = app(DocumentExpirationService::class)->scan();

        $this->assertSame(4, $first['notifications_created']);
        $this->assertSame(0, $second['notifications_created']);
        $this->assertSame(4, AssessmentNotification::where('type', 'document_expiration')->count());
        $this->assertEqualsCanonicalizing([$admin->id, $qa->id], AssessmentNotification::pluck('recipient_id')->unique()->values()->all());
    }

    public function test_unauthorized_employee_cannot_access_compliance_register(): void
    {
        $agent = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $this->actingAs($agent)->get('/document-expirations')->assertForbidden();
        $this->post('/document-expirations/scan')->assertForbidden();
    }
}
