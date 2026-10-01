<?php

namespace Tests\Feature;

use App\Mail\PayslipMail;
use App\Models\AccountingEntry;
use App\Models\User;
use App\Services\PayslipPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayslipEmailTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function paidPayroll(User $employee, array $changes = []): AccountingEntry
    {
        return AccountingEntry::create([...[
            'request_key' => (string) Str::uuid(),
            'user_id' => $employee->id,
            'created_by' => $employee->id,
            'kind' => 'payroll',
            'description' => 'September payroll',
            'status' => 'paid',
            'period_start' => '2026-09-11',
            'period_end' => '2026-09-25',
            'paid_at' => '2026-09-30',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'PAY-260930-001',
            'gross_cents' => 388508,
            'deduction_cents' => 50000,
            'net_cents' => 338508,
            'source_snapshot' => [
                'employee_name' => 'Recorded Employee',
                'employee_id' => 'DVX0099',
                'payroll' => [
                    'pay_date' => '2026-09-30',
                    'earnings' => [
                        ['label' => 'Basic pay', 'rate' => '65.9100', 'hours' => '41', 'amount_cents' => 270231],
                        ['label' => 'Variable allowance', 'rate' => '18.4000', 'hours' => '41', 'amount_cents' => 75436],
                        ['label' => 'Night differential', 'rate' => '6.5900', 'hours' => '41', 'amount_cents' => 27023],
                    ],
                    'deductions' => [
                        ['label' => 'SSS', 'amount_cents' => 30000],
                        ['label' => 'PhilHealth', 'amount_cents' => 10000],
                        ['label' => 'Pag-IBIG', 'amount_cents' => 10000],
                    ],
                ],
            ],
        ], ...$changes]);
    }

    public function test_admin_and_accounting_can_email_a_branded_pdf_to_the_employee_account_email(): void
    {
        Mail::fake();
        $employee = $this->user('agent');
        $entry = $this->paidPayroll($employee);

        foreach (['admin', 'accounting'] as $role) {
            $this->actingAs($this->user($role))
                ->post('/accounting/entries/'.$entry->id.'/email-payslip')
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', 'Payslip PDF sent to '.$employee->email.'.');
        }

        Mail::assertSent(PayslipMail::class, 2);
        Mail::assertSent(PayslipMail::class, function (PayslipMail $mail) use ($employee) {
            return $mail->hasTo($employee->email)
                && str_starts_with($mail->pdf, '%PDF-')
                && str_starts_with($mail->filename, 'Divertex-Payslip-')
                && count($mail->attachments()) === 1;
        });
        $this->assertDatabaseCount('assessment_activity_logs', 2);
        $this->assertDatabaseHas('assessment_activity_logs', [
            'target_id' => $entry->id,
            'action' => 'Payroll payslip emailed',
        ]);
    }

    public function test_pdf_uses_the_official_company_identity_and_locked_payroll_breakdown(): void
    {
        $entry = $this->paidPayroll($this->user('agent'));
        $service = app(PayslipPdfService::class);
        $html = $service->html($entry);

        $this->assertStringContainsString('Divertex Global Strategies Corporation', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('Recorded Employee', $html);
        $this->assertStringContainsString('Basic pay', $html);
        $this->assertStringContainsString('PhilHealth', $html);
        $this->assertStringContainsString('PAY-260930-001', $html);
        $this->assertStringStartsWith('%PDF-', $service->render($entry));
    }

    public function test_non_finance_users_and_non_paid_payroll_records_cannot_send_payslips(): void
    {
        Mail::fake();
        $entry = $this->paidPayroll($this->user('agent'));
        $url = '/accounting/entries/'.$entry->id.'/email-payslip';

        $this->actingAs($this->user('team_leader'))->post($url)->assertForbidden();

        $this->actingAs($this->user('admin'));
        foreach ([
            ['status' => 'approved'],
            ['status' => 'draft'],
            ['kind' => 'training_allowance'],
        ] as $changes) {
            $blocked = $this->paidPayroll($this->user('agent'), $changes);
            $this->post('/accounting/entries/'.$blocked->id.'/email-payslip')
                ->assertSessionHasErrors('payslip');
        }

        Mail::assertNothingSent();
    }
}
