<?php

namespace Tests\Feature;

use App\Models\EmployeeBankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeBankAccountTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'accounting'): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active']);
        if ($role === 'trainee') {
            $user->forceFill(['training_status' => 'in_training'])->save();
        }
        $this->actingAs($user);

        return $user;
    }

    private function payload(User $employee, array $extra = []): array
    {
        return [...['request_key' => (string) Str::uuid(), 'user_id' => $employee->id, 'party_type' => 'payee', 'bank_name' => 'Test Bank', 'branch' => 'Main', 'account_holder' => 'Private Holder', 'account_number' => '001234567890', 'notes' => 'Private bank notes'], ...$extra];
    }

    public function test_bank_details_are_encrypted_masked_and_not_copied_to_audit_logs(): void
    {
        $employee = $this->actor('agent');
        $this->actor();
        $data = $this->payload($employee);
        $this->post('/accounting/bank-accounts', $data)->assertSessionHasNoErrors();
        $record = EmployeeBankAccount::sole();
        $raw = DB::table('employee_bank_accounts')->first();
        foreach (['account_number', 'account_holder', 'notes'] as $field) {
            $this->assertNotSame($data[$field], $raw->$field);
            $this->assertSame($data[$field], $record->$field);
            $this->assertArrayNotHasKey($field, $record->toArray());
        }
        $list = $this->getJson('/accounting/bank-accounts')->assertOk()->assertJsonPath('accounts.data.0.masked_number', '•••• 7890');
        $this->assertStringNotContainsString($data['account_number'], $list->getContent());
        $this->assertStringNotContainsString($data['account_holder'], $list->getContent());
        $this->getJson('/accounting/bank-accounts/'.$record->id)->assertOk()->assertJsonPath('account_number', $data['account_number'])->assertHeader('Cache-Control', 'no-store, private');
        foreach (DB::table('assessment_activity_logs')->pluck('metadata') as $metadata) {
            $this->assertStringNotContainsString($data['account_number'], $metadata);
        }
    }

    public function test_bank_crud_is_restricted_to_admin_and_accounting(): void
    {
        $this->get('/accounting/bank-accounts')->assertRedirect('/login');
        $employee = $this->actor('agent');
        $this->actor('admin');
        $data = $this->payload($employee);
        $this->post('/accounting/bank-accounts', $data)->assertSessionHasNoErrors();
        $id = EmployeeBankAccount::sole()->id;
        foreach (['agent', 'team_leader', 'qa_admin', 'manager', 'trainee'] as $role) {
            $this->actor($role);
            $this->getJson('/accounting/bank-accounts')->assertForbidden();
            $this->getJson('/accounting/bank-accounts/'.$id)->assertForbidden();
            $this->post('/accounting/bank-accounts', $data)->assertForbidden();
            $this->patch('/accounting/bank-accounts/'.$id, [...$data, 'version' => 1])->assertForbidden();
            $this->delete('/accounting/bank-accounts/'.$id, ['version' => 1])->assertForbidden();
        }
    }

    public function test_edits_and_deletes_preserve_other_records_and_reject_stale_versions(): void
    {
        $employee = $this->actor('agent');
        $this->actor();
        $data = $this->payload($employee);
        $this->post('/accounting/bank-accounts', $data);
        $record = EmployeeBankAccount::sole();
        $url = '/accounting/bank-accounts/'.$record->id;
        $this->post('/accounting/bank-accounts', $this->payload($employee, ['account_number' => '000099998888']))->assertSessionHasNoErrors();
        $this->patch($url, [...$data, 'version' => 1, 'party_type' => 'both', 'branch' => 'New branch'])->assertSessionHasNoErrors();
        $this->assertSame('both', $record->fresh()->party_type);
        $this->patch($url, [...$data, 'version' => 1])->assertSessionHasErrors('version');
        $this->delete($url, ['version' => 1])->assertSessionHasErrors('version');
        $this->getJson('/accounting/bank-accounts?party_type=payor')->assertJsonPath('accounts.total', 1);
        $this->delete($url, ['version' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('employee_bank_accounts', ['id' => $record->id]);
        $this->assertDatabaseCount('employee_bank_accounts', 1);
        $this->assertDatabaseHas('users', ['id' => $employee->id]);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Bank information deleted', 'target_id' => $record->id]);
    }

    public function test_duplicate_and_invalid_records_are_rejected_without_flashing_bank_details(): void
    {
        $employee = $this->actor('agent');
        $this->actor();
        $data = $this->payload($employee);
        $this->post('/accounting/bank-accounts', $data)->assertSessionHasNoErrors();
        $this->post('/accounting/bank-accounts', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('employee_bank_accounts', 1);
        $this->post('/accounting/bank-accounts', $this->payload($employee, ['bank_name' => 'test bank', 'account_number' => '0012-3456-7890']))->assertSessionHasErrors('account_number')->assertSessionMissing('_old_input.account_number')->assertSessionMissing('_old_input.account_holder')->assertSessionMissing('_old_input.notes');
        $this->post('/accounting/bank-accounts', $this->payload($employee, ['party_type' => 'invalid']))->assertSessionHasErrors('party_type');
        $this->post('/accounting/bank-accounts', $this->payload($employee, ['user_id' => 999999]))->assertSessionHasErrors('user_id');
    }
}
