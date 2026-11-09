<?php

namespace Tests\Feature;

use App\Models\AccountingEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GovernmentTaxReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_can_generate_and_export_government_and_tax_reports(): void
    {
        $accountant = User::factory()->create(['role' => 'accounting', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'agent', 'status' => 'active', 'username' => 'DVX321']);
        AccountingEntry::create([
            'request_key' => (string) Str::uuid(),
            'kind' => 'payroll',
            'user_id' => $employee->id,
            'description' => 'January payroll',
            'period_start' => '2026-01-11',
            'period_end' => '2026-01-25',
            'gross_cents' => 500000,
            'deduction_cents' => 90000,
            'net_cents' => 410000,
            'status' => 'paid',
            'paid_at' => '2026-01-31',
            'created_by' => $accountant->id,
            'source_snapshot' => ['payroll' => ['deductions' => [
                ['label' => 'SSS', 'amount_cents' => 20000],
                ['label' => 'PhilHealth', 'amount_cents' => 15000],
                ['label' => 'Pag-IBIG', 'amount_cents' => 10000],
                ['label' => 'BIR withholding tax', 'amount_cents' => 45000],
            ]]],
        ]);

        $this->actingAs($accountant)
            ->getJson('/accounting/government-reports?year=2026')
            ->assertOk()
            ->assertJsonPath('totals.employees', 1)
            ->assertJsonPath('totals.gross_cents', 500000)
            ->assertJsonPath('totals.sss_cents', 20000)
            ->assertJsonPath('totals.philhealth_cents', 15000)
            ->assertJsonPath('totals.pagibig_cents', 10000)
            ->assertJsonPath('totals.withholding_tax_cents', 45000)
            ->assertJsonPath('rows.0.taxable_compensation_cents', 455000)
            ->assertJsonPath('rows.0.missing_identifiers.0', 'TIN');

        $this->put('/accounting/government-details/'.$employee->id, [
            'tin' => '123-456-789-000',
            'rdo_code' => '043',
            'sss_number' => '12-3456789-0',
            'philhealth_number' => '12-345678901-2',
            'pagibig_number' => '1234-5678-9012',
        ])->assertSessionHasNoErrors();

        $this->getJson('/accounting/government-reports?year=2026&month=1')
            ->assertOk()
            ->assertJsonPath('rows.0.tin', '123-456-789-000')
            ->assertJsonCount(0, 'rows.0.missing_identifiers');

        $response = $this->get('/accounting/government-reports/export?year=2026&type=bir_2316');
        $response->assertOk()->assertDownload('Divertex-bir_2316-2026-annual.csv');
        $this->assertStringContainsString('Taxable compensation basis', $response->streamedContent());
        $this->assertDatabaseHas('activity_logs', ['action' => 'Government report exported', 'actor_id' => $accountant->id]);
    }

    public function test_reports_are_restricted_to_admin_and_accounting_roles(): void
    {
        $this->getJson('/accounting/government-reports?year=2026')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'agent', 'status' => 'active']))
            ->getJson('/accounting/government-reports?year=2026')->assertForbidden();
    }
}
