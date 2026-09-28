<?php

namespace Tests\Feature;

use App\Models\AccountingExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $role = 'accounting'): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active']);
        if ($role === 'trainee') {
            $user->forceFill(['training_status' => 'in_training'])->save();
        }
        $this->actingAs($user);

        return $user;
    }

    private function category(string $name = 'Utilities'): int
    {
        $this->post('/accounting/expense-categories', ['name' => $name])->assertSessionHasNoErrors();

        return DB::table('expense_categories')->where('name', $name)->value('id');
    }

    private function payload(int $category, array $extra = []): array
    {
        return [...['request_key' => (string) Str::uuid(), 'category_id' => $category, 'expense_date' => '2026-01-10', 'description' => 'Electricity', 'amount' => '1234.56', 'payee' => 'Supplier', 'reference' => 'INV-001', 'notes' => 'Office'], ...$extra];
    }

    public function test_only_admin_and_accounting_can_read_or_write_expenses(): void
    {
        $this->get('/accounting/expenses')->assertRedirect('/login');
        foreach (['admin', 'accounting'] as $role) {
            $this->login($role);
            $this->getJson('/accounting/expenses')->assertOk();
        }
        $category = $this->category();
        $this->post('/accounting/expenses', $this->payload($category))->assertSessionHasNoErrors();
        $id = AccountingExpense::sole()->id;
        foreach (['agent', 'team_leader', 'qa_admin', 'trainee', 'manager'] as $role) {
            $this->login($role);
            $this->getJson('/accounting/expenses')->assertForbidden();
            $this->post('/accounting/expenses', $this->payload($category))->assertForbidden();
            $this->post('/accounting/expense-categories', ['name' => 'Blocked'])->assertForbidden();
            $this->patch('/accounting/expense-categories/'.$category, ['name' => 'Blocked'])->assertForbidden();
            $this->patch('/accounting/expenses/'.$id.'/void', ['void_reason' => 'Blocked'])->assertForbidden();
        }
    }

    public function test_categories_can_be_renamed_without_losing_expenses_and_duplicates_are_rejected(): void
    {
        $this->login();
        $id = $this->category();
        $this->post('/accounting/expense-categories', ['name' => '  UTILITIES  '])->assertSessionHasErrors('name_key');
        $this->post('/accounting/expenses', $this->payload($id))->assertSessionHasNoErrors();
        $this->patch('/accounting/expense-categories/'.$id, ['name' => 'Office Utilities'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('expense_categories', ['id' => $id, 'name' => 'Office Utilities']);
        $this->assertSame($id, AccountingExpense::sole()->category_id);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Expense category saved']);
    }

    public function test_expenses_use_exact_amounts_and_filters_group_totals_without_duplicate_submissions(): void
    {
        $this->login();
        $utilities = $this->category();
        $rent = $this->category('Rent');
        $data = $this->payload($utilities);
        $this->post('/accounting/expenses', $data)->assertSessionHasNoErrors();
        $this->post('/accounting/expenses', $data)->assertSessionHasNoErrors();
        $this->post('/accounting/expenses', $this->payload($rent, ['amount' => '500.25']))->assertSessionHasNoErrors();
        $this->post('/accounting/expenses', $this->payload($utilities, ['expense_date' => '2025-12-01', 'amount' => '10']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('accounting_expenses', 3);
        $this->getJson('/accounting/expenses?from=2026-01-01&to=2026-01-31&category_id='.$utilities)->assertOk()->assertJsonPath('filtered_total_cents', 123456)->assertJsonPath('expenses.total', 1)->assertJsonCount(2, 'totals');
        $this->getJson('/accounting/expenses?to=2026-01-31')->assertOk()->assertJsonPath('filtered_total_cents', 174481);
        foreach (['0', '-1', '12.345', 'abc'] as $amount) {
            $this->post('/accounting/expenses', $this->payload($utilities, ['amount' => $amount]))->assertSessionHasErrors('amount');
        }
        $this->post('/accounting/expenses', $this->payload(999999))->assertSessionHasErrors('category_id');
        $this->getJson('/accounting/expenses?from=2026-02-01&to=2026-01-01')->assertUnprocessable();
    }

    public function test_void_preserves_history_excludes_totals_and_retains_the_recording_account(): void
    {
        $this->login();
        $this->post('/accounting/expenses', $this->payload($this->category()))->assertSessionHasNoErrors();
        $entry = AccountingExpense::sole();
        $this->delete('/settings/profile', ['password' => 'password'])->assertForbidden();
        $url = '/accounting/expenses/'.$entry->id.'/void';
        $this->patch($url, ['void_reason' => ''])->assertSessionHasErrors('void_reason');
        $this->patch($url, ['void_reason' => 'Entered wrong amount'])->assertSessionHasNoErrors();
        $this->getJson('/accounting/expenses')->assertJsonPath('filtered_total_cents', 0)->assertJsonPath('expenses.total', 1)->assertJsonPath('expenses.data.0.void_reason', 'Entered wrong amount');
        $this->patch($url, ['void_reason' => 'Try again'])->assertSessionHasErrors('void_reason');
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Expense voided', 'target_id' => $entry->id]);
    }
}
