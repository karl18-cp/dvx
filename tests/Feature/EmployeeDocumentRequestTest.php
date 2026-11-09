<?php

namespace Tests\Feature;

use App\Mail\EmployeeDocumentReady;
use App\Models\EmployeeDocumentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeDocumentRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        Mail::fake();
    }

    private function payload(array $changes = []): array
    {
        return array_replace([
            'request_key' => (string) Str::uuid(), 'document_type' => 'certificate_of_employment',
            'other_document_type' => '', 'purpose' => 'Requirement for a bank application.',
            'period_start' => '', 'period_end' => '', 'delivery_method' => 'portal', 'employee_notes' => '',
        ], $changes);
    }

    public function test_employee_can_submit_and_only_sees_own_requests_while_admin_sees_all(): void
    {
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $other = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        EmployeeDocumentRequest::create([...$this->payload(), 'user_id' => $other->id, 'status' => 'submitted', 'submitted_at' => now()]);

        $this->actingAs($employee)->post('/document-requests', $this->payload())->assertSessionHasNoErrors();
        $this->get('/document-requests')->assertOk()->assertInertia(fn ($page) => $page
            ->component('employee-document-requests')->where('canManage', false)->where('scope', 'mine')->where('requests.total', 1)->where('requests.data.0.user_id', $employee->id));
        $this->actingAs($admin)->get('/document-requests')->assertOk()->assertInertia(fn ($page) => $page->where('canManage', true)->where('scope', 'all')->where('requests.total', 2));
        $this->assertDatabaseHas('activity_logs', ['category' => 'documents', 'subject_id' => $employee->id, 'action' => 'Employee document requested']);
        $this->assertDatabaseHas('assessment_notifications', ['recipient_id' => $admin->id, 'type' => 'document_request']);
    }

    public function test_payslip_copy_requires_a_valid_period(): void
    {
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);

        $this->actingAs($employee)->post('/document-requests', $this->payload(['document_type' => 'payslip_copy']))
            ->assertSessionHasErrors(['period_start', 'period_end']);
        $this->post('/document-requests', $this->payload(['document_type' => 'payslip_copy', 'period_start' => '2026-10-26', 'period_end' => '2026-10-25']))
            ->assertSessionHasErrors('period_end');
    }

    public function test_admin_can_release_private_document_and_email_attachment(): void
    {
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active', 'email' => 'employee@divertexcorp.com']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $request = EmployeeDocumentRequest::create([...$this->payload(['delivery_method' => 'both']), 'user_id' => $employee->id, 'status' => 'submitted', 'submitted_at' => now()]);

        $this->actingAs($admin)->post("/document-requests/{$request->id}/process", [
            'status' => 'released', 'reviewer_notes' => 'Approved and signed.',
            'document' => UploadedFile::fake()->create('employment-certificate.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('released', $request->status);
        Storage::disk('local')->assertExists($request->file_path);
        Mail::assertSent(EmployeeDocumentReady::class, fn ($mail) => $mail->hasTo($employee->email));
        $this->assertNotNull($request->email_sent_at);
        $this->actingAs($employee)->get("/document-requests/{$request->id}/download")->assertOk();

        $outsider = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $this->actingAs($outsider)->get("/document-requests/{$request->id}/download")->assertForbidden();
    }

    public function test_ready_status_requires_an_uploaded_document(): void
    {
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $request = EmployeeDocumentRequest::create([...$this->payload(), 'user_id' => $employee->id, 'status' => 'submitted', 'submitted_at' => now()]);

        $this->actingAs($admin)->post("/document-requests/{$request->id}/process", ['status' => 'ready', 'reviewer_notes' => 'Prepared.'])
            ->assertSessionHasErrors('document');
        $this->assertSame('submitted', $request->fresh()->status);
    }

    public function test_employee_can_cancel_only_a_new_request_and_trainee_cannot_submit(): void
    {
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active']);
        $request = EmployeeDocumentRequest::create([...$this->payload(), 'user_id' => $employee->id, 'status' => 'submitted', 'submitted_at' => now()]);
        $this->actingAs($employee)->patch("/document-requests/{$request->id}/cancel")->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $request->fresh()->status);
        $this->patch("/document-requests/{$request->id}/cancel")->assertSessionHasErrors('status');

        $trainee = User::factory()->create(['role' => 'trainee', 'status' => 'active', 'training_status' => 'in_training']);
        $this->actingAs($trainee)->post('/document-requests', $this->payload())->assertForbidden();
    }
}
