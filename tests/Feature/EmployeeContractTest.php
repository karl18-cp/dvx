<?php

namespace Tests\Feature;

use App\Models\EmployeeContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutVite();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'active',
            'username' => strtoupper(substr($role, 0, 3)).Str::upper(Str::random(7)),
        ]);
    }

    private function payload(User $employee, array $changes = []): array
    {
        return [...[
            'request_key' => (string) Str::uuid(),
            'user_id' => $employee->id,
            'contract_type' => 'employment',
            'title' => 'Regular employment agreement',
            'reference_number' => 'EMP-2026-001',
            'effective_date' => '2026-10-01',
            'end_date' => '',
            'status' => 'active',
            'notes' => 'Signed after onboarding.',
            'document' => UploadedFile::fake()->create('employment-contract.pdf', 120, 'application/pdf'),
        ], ...$changes];
    }

    public function test_admin_and_accounting_can_store_employee_and_training_contracts(): void
    {
        foreach (['admin', 'accounting'] as $role) {
            $employee = $this->user($role === 'admin' ? 'agent' : 'trainee');
            $this->actingAs($this->user($role))
                ->post('/accounting/contracts', $this->payload($employee, [
                    'contract_type' => $employee->role === 'trainee' ? 'training' : 'employment',
                    'title' => $employee->role === 'trainee' ? 'Initial training agreement' : 'Employment agreement',
                ]))
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('employee_contracts', 2);
        foreach (EmployeeContract::all() as $contract) {
            Storage::disk('local')->assertExists($contract->file_path);
        }
        $this->assertDatabaseCount('assessment_activity_logs', 2);
    }

    public function test_contracts_are_private_linked_searchable_and_downloadable(): void
    {
        $employee = $this->user('agent');
        $accountant = $this->user('accounting');
        $this->actingAs($accountant)->post('/accounting/contracts', $this->payload($employee));
        $contract = EmployeeContract::sole();

        $response = $this->getJson('/accounting/contracts?search='.$employee->username)
            ->assertOk()
            ->assertJsonPath('records.data.0.user_id', $employee->id)
            ->assertJsonPath('records.data.0.employee.name', $employee->name)
            ->assertJsonMissingPath('records.data.0.file_path');
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->getJson('/accounting/contracts/'.$contract->id)
            ->assertOk()
            ->assertJsonPath('employee.id', $employee->id)
            ->assertJsonMissingPath('file_path');
        $this->get('/accounting/contracts/'.$contract->id.'/document')
            ->assertOk()
            ->assertDownload('employment-contract.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($this->user('agent'));
        $this->getJson('/accounting/contracts')->assertForbidden();
        $this->get('/accounting/contracts/'.$contract->id.'/document')->assertForbidden();
        $this->get('/accounting/workspace/contracts')->assertForbidden();
    }

    public function test_contract_metadata_and_file_can_be_updated_then_archived_without_deleting_the_document(): void
    {
        $employee = $this->user('agent');
        $this->actingAs($this->user('admin'))->post('/accounting/contracts', $this->payload($employee));
        $contract = EmployeeContract::sole();
        $oldPath = $contract->file_path;

        $this->post('/accounting/contracts/'.$contract->id.'/update', $this->payload($employee, [
            'request_key' => '',
            'title' => 'Updated employment agreement',
            'reference_number' => 'EMP-2026-UPDATED',
            'document' => UploadedFile::fake()->create('revised-contract.docx', 80, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ]))->assertSessionHasNoErrors();

        $contract->refresh();
        $this->assertSame('Updated employment agreement', $contract->title);
        $this->assertSame('revised-contract.docx', $contract->original_filename);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($contract->file_path);

        $this->patch('/accounting/contracts/'.$contract->id.'/archive', ['archive_reason' => 'Replaced by a new signed agreement.'])
            ->assertSessionHasNoErrors();
        $contract->refresh();
        $this->assertNotNull($contract->archived_at);
        Storage::disk('local')->assertExists($contract->file_path);
        $this->getJson('/accounting/contracts?status=archived')->assertJsonPath('records.data.0.id', $contract->id);
        $this->post('/accounting/contracts/'.$contract->id.'/update', $this->payload($employee, ['request_key' => '', 'document' => null]))
            ->assertSessionHasErrors('contract');
        $this->assertDatabaseHas('assessment_activity_logs', ['target_id' => $contract->id, 'action' => 'Employee contract archived']);
    }

    public function test_contract_validation_rejects_unsafe_files_and_invalid_dates_and_blocks_account_deletion(): void
    {
        $employee = $this->user('agent');
        $this->actingAs($this->user('accounting'));
        $this->post('/accounting/contracts', $this->payload($employee, [
            'end_date' => '2026-09-30',
            'document' => UploadedFile::fake()->create('contract.exe', 10, 'application/octet-stream'),
        ]))->assertSessionHasErrors(['end_date', 'document']);

        $this->post('/accounting/contracts', $this->payload($employee))->assertSessionHasNoErrors();
        $this->actingAs($employee)->delete('/settings/profile', ['password' => 'password'])->assertForbidden();
    }
}
