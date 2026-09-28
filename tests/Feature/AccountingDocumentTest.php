<?php

namespace Tests\Feature;

use App\Models\AccountingDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $role = 'accounting'): void
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active']);
        if ($role === 'trainee') {
            $user->forceFill(['training_status' => 'in_training'])->save();
        }
        $this->actingAs($user);
    }

    private function record(array $extra = []): array
    {
        return [...['request_key' => (string) Str::uuid(), 'record_type' => 'invoices', 'direction' => 'receivable', 'invoice_number' => 'INV-001', 'party_name' => 'Test client', 'description' => 'Services', 'issue_date' => '2026-01-01', 'due_date' => '2026-12-31', 'amount' => '1000.25'], ...$extra];
    }

    private function payment(array $extra = []): array
    {
        return [...['request_key' => (string) Str::uuid(), 'amount' => '400.10', 'payment_date' => '2026-02-01', 'method' => 'bank_transfer', 'reference' => 'REF-001'], ...$extra];
    }

    public function test_invoices_link_to_the_correct_ledger_and_totals_are_exact(): void
    {
        $this->login();
        $data = $this->record();
        $this->post('/accounting/documents', $data)->assertSessionHasNoErrors();
        $this->post('/accounting/documents', $data)->assertSessionHasNoErrors();
        $this->post('/accounting/documents', $this->record(['direction' => 'payable', 'party_name' => 'Supplier', 'amount' => '250.75']))->assertSessionHasNoErrors();
        $this->post('/accounting/documents', $this->record(['record_type' => 'receivable', 'invoice_number' => null, 'amount' => '10']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('accounting_documents', 3);
        $this->getJson('/accounting/documents?view=invoices')->assertOk()->assertJsonPath('records.total', 2)->assertJsonPath('summary.total_cents', 125100);
        $this->getJson('/accounting/documents?view=receivable')->assertJsonPath('records.total', 2)->assertJsonPath('summary.balance_cents', 101025);
        $this->getJson('/accounting/documents?view=payable')->assertJsonPath('records.total', 1)->assertJsonPath('summary.balance_cents', 25075);
        $this->post('/accounting/documents', $this->record())->assertSessionHasErrors('invoice_number');
    }

    public function test_partial_payments_settle_balances_and_prevent_overpayment_or_replay(): void
    {
        $this->login();
        $this->post('/accounting/documents', $this->record());
        $id = AccountingDocument::sole()->id;
        $url = '/accounting/documents/'.$id;
        $data = $this->payment();
        $this->post($url.'/payments', $data)->assertSessionHasNoErrors();
        $this->post($url.'/payments', $data)->assertSessionHasNoErrors();
        $this->getJson($url)->assertJsonPath('paid_cents', 40010)->assertJsonPath('balance_cents', 60015)->assertJsonPath('status', 'partial')->assertJsonCount(1, 'payments');
        $this->post($url.'/payments', $this->payment(['amount' => '600.16']))->assertSessionHasErrors('amount');
        $this->patch($url.'/void', ['void_reason' => 'Has real payment'])->assertSessionHasErrors('void_reason');
        $this->post($url.'/payments', $this->payment(['amount' => '600.15']))->assertSessionHasNoErrors();
        $this->getJson($url)->assertJsonPath('status', 'settled')->assertJsonPath('balance_cents', 0);
        $this->getJson('/accounting/documents?view=receivable&status=settled')->assertJsonPath('records.total', 1)->assertJsonPath('summary.paid_cents', 100025);
        $this->assertDatabaseHas('assessment_activity_logs', ['action' => 'Document payment recorded', 'target_id' => $id]);
    }

    public function test_overdue_and_void_records_and_replacement_invoice(): void
    {
        $this->login();
        $this->post('/accounting/documents', $this->record(['due_date' => '2026-01-02']));
        $id = AccountingDocument::sole()->id;
        $this->getJson('/accounting/documents?view=invoices&status=overdue')->assertJsonPath('records.total', 1);
        $this->patch('/accounting/documents/'.$id.'/void', ['void_reason' => 'Incorrect invoice total'])->assertSessionHasNoErrors();
        $this->getJson('/accounting/documents?view=invoices')->assertJsonPath('summary.balance_cents', 0)->assertJsonPath('records.data.0.status', 'void');
        $this->post('/accounting/documents/'.$id.'/payments', $this->payment())->assertSessionHasErrors('amount');
        $this->post('/accounting/documents', $this->record())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('accounting_documents', 2);
    }

    public function test_private_attachment_and_all_routes_are_restricted(): void
    {
        Storage::fake('local');
        $this->login('admin');
        $this->post('/accounting/documents', $this->record(['attachment' => UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf')]))->assertSessionHasNoErrors();
        $document = AccountingDocument::sole();
        Storage::disk('local')->assertExists($document->attachment_path);
        $url = '/accounting/documents/'.$document->id;
        $this->get($url.'/attachment')->assertOk()->assertDownload('invoice.pdf');
        $this->getJson($url)->assertJsonMissingPath('attachment_path');
        foreach (['agent', 'team_leader', 'qa_admin', 'manager', 'trainee'] as $role) {
            $this->login($role);
            $this->getJson('/accounting/documents?view=invoices')->assertForbidden();
            $this->getJson($url)->assertForbidden();
            $this->get($url.'/attachment')->assertForbidden();
            $this->post('/accounting/documents', $this->record())->assertForbidden();
            $this->post($url.'/payments', $this->payment())->assertForbidden();
            $this->patch($url.'/void', ['void_reason' => 'Unauthorized'])->assertForbidden();
        }
    }

    public function test_invalid_inputs_are_rejected(): void
    {
        $this->login();
        $this->post('/accounting/documents', $this->record(['invoice_number' => null]))->assertSessionHasErrors('invoice_number');
        $this->post('/accounting/documents', $this->record(['amount' => '-10']))->assertSessionHasErrors('amount');
        $this->post('/accounting/documents', $this->record(['due_date' => '2025-12-01']))->assertSessionHasErrors('due_date');
        $this->post('/accounting/documents', $this->record(['attachment' => UploadedFile::fake()->create('script.php', 10, 'text/plain')]))->assertSessionHasErrors('attachment');
        $this->assertDatabaseCount('accounting_documents', 0);
    }
}
