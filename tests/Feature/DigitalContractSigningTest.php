<?php

namespace Tests\Feature;

use App\Models\EmployeeContract;
use App\Models\EmployeeContractSignature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DigitalContractSigningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function contract(User $employee): EmployeeContract
    {
        $path = "employee-contracts/{$employee->id}/contract.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4 test contract version one');

        return EmployeeContract::create([
            'request_key' => Str::uuid(), 'version_group' => (string) Str::uuid(), 'version' => 1,
            'user_id' => $employee->id, 'contract_type' => 'employment', 'title' => 'Employment Agreement',
            'reference_number' => 'EA-100', 'effective_date' => '2026-11-10', 'status' => 'draft', 'signing_status' => 'draft',
            'file_disk' => 'local', 'file_path' => $path, 'original_filename' => 'employment-agreement.pdf',
            'mime_type' => 'application/pdf', 'file_size' => Storage::disk('local')->size($path),
            'document_sha256' => hash_file('sha256', Storage::disk('local')->path($path)),
        ]);
    }

    public function test_admin_issues_exact_version_and_employee_can_sign_and_download_signed_pdf(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active', 'name' => 'Jane Divertex']);
        $contract = $this->contract($employee);

        $this->actingAs($admin)->post("/accounting/contracts/{$contract->id}/issue", ['signature_due_at' => now()->addDays(5)->toDateTimeString()])->assertSessionHasNoErrors();
        $contract->refresh();
        $this->assertSame('issued', $contract->signing_status);
        $this->assertNotNull($contract->immutable_at);
        $this->assertDatabaseHas('assessment_notifications', ['recipient_id' => $employee->id, 'type' => 'contract']);

        $this->actingAs($employee)->get('/my-contracts')->assertOk()->assertInertia(fn ($page) => $page
            ->component('my-contracts')->where('contracts.0.id', $contract->id)->where('contracts.0.version', 1)->where('contracts.0.signing_status', 'issued'));
        $this->post("/my-contracts/{$contract->id}/sign", ['signature_name' => 'Wrong Name', 'consent' => true])->assertSessionHasErrors('signature_name');
        $this->post("/my-contracts/{$contract->id}/sign", ['signature_name' => 'Jane Divertex', 'consent' => true])->assertSessionHasNoErrors();

        $contract->refresh();
        $signature = EmployeeContractSignature::sole();
        $this->assertSame('signed', $contract->signing_status);
        $this->assertSame($employee->id, $signature->signer_id);
        $this->assertSame($contract->document_sha256, $signature->document_sha256);
        Storage::disk('local')->assertExists($contract->signed_pdf_path);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($contract->signed_pdf_path)), $contract->signed_pdf_sha256);
        $this->get("/my-contracts/{$contract->id}/signed")->assertOk();
        $this->get("/my-contracts/{$contract->id}/original")->assertOk();
        $this->assertDatabaseHas('activity_logs', ['category' => 'contracts', 'action' => 'Contract digitally signed', 'subject_id' => $employee->id]);
    }

    public function test_issued_source_is_immutable_and_manager_can_create_next_version(): void
    {
        $accounting = User::factory()->create(['role' => 'accounting', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'trainee', 'status' => 'active', 'training_status' => 'in_training', 'username' => 'DVXTR900']);
        $contract = $this->contract($employee);
        $this->actingAs($accounting)->post("/accounting/contracts/{$contract->id}/issue")->assertSessionHasNoErrors();

        $this->post("/accounting/contracts/{$contract->id}/update", [
            'user_id' => $employee->id, 'contract_type' => 'employment', 'title' => 'Changed title',
            'effective_date' => '2026-11-10', 'status' => 'active',
        ])->assertSessionHasErrors('contract');

        $this->post("/accounting/contracts/{$contract->id}/versions", [
            'document' => UploadedFile::fake()->create('employment-v2.pdf', 50, 'application/pdf'), 'notes' => 'Revised terms.',
        ])->assertSessionHasNoErrors();
        $version = EmployeeContract::where('version_group', $contract->version_group)->where('version', 2)->sole();
        $this->assertSame('draft', $version->signing_status);
        Storage::disk('local')->assertExists($version->file_path);

        $this->post("/accounting/contracts/{$version->id}/issue")->assertSessionHasNoErrors();
        $this->assertSame('superseded', $contract->fresh()->signing_status);
        $this->assertSame('issued', $version->fresh()->signing_status);
    }

    public function test_only_assigned_signer_can_access_or_sign_contract(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $outsider = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $contract = $this->contract($employee);
        $this->actingAs($admin)->post("/accounting/contracts/{$contract->id}/issue");

        $this->actingAs($outsider)->get("/my-contracts/{$contract->id}/original")->assertForbidden();
        $this->post("/my-contracts/{$contract->id}/sign", ['signature_name' => $outsider->name, 'consent' => true])->assertForbidden();
        $this->actingAs($employee)->post("/my-contracts/{$contract->id}/sign", ['signature_name' => $employee->name, 'consent' => false])->assertSessionHasErrors('consent');
    }

    public function test_assigned_employee_can_decline_with_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $contract = $this->contract($employee);
        $this->actingAs($admin)->post("/accounting/contracts/{$contract->id}/issue");

        $this->actingAs($employee)->post("/my-contracts/{$contract->id}/decline", ['reason' => 'Please correct the position title.'])->assertSessionHasNoErrors();
        $this->assertSame('declined', $contract->fresh()->signing_status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Contract signature declined', 'subject_id' => $employee->id]);
    }
}
