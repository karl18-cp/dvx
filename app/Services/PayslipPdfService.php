<?php

namespace App\Services;

use App\Models\AccountingEntry;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;

class PayslipPdfService
{
    public const COMPANY_NAME = 'Divertex Global Strategies Corporation';

    public function render(AccountingEntry $entry): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->html($entry), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return $pdf->output();
    }

    public function html(AccountingEntry $entry): string
    {
        $entry->loadMissing('employee:id,name,username,email,role');
        $snapshot = $entry->source_snapshot ?? [];
        $payroll = isset($snapshot['payroll']) && is_array($snapshot['payroll']) ? $snapshot['payroll'] : [];
        $earnings = $this->earnings($payroll['earnings'] ?? null, $entry->gross_cents);
        $deductions = $this->deductions($payroll['deductions'] ?? null, $entry->deduction_cents);
        $logo = public_path('images/divertex-logo.png');
        $logoContents = is_file($logo) ? file_get_contents($logo) : false;

        return view('pdf.payslip', [
            'companyName' => self::COMPANY_NAME,
            'logoDataUri' => is_string($logoContents) ? 'data:image/png;base64,'.base64_encode($logoContents) : null,
            'employeeName' => isset($snapshot['employee_name']) ? (string) $snapshot['employee_name'] : $entry->employee->name,
            'employeeId' => isset($snapshot['employee_id']) ? (string) $snapshot['employee_id'] : $entry->employee->username,
            'position' => $this->roleLabel($entry->employee->role),
            'payDate' => Carbon::parse($payroll['pay_date'] ?? $entry->paid_at)->format('M d, Y'),
            'periodStart' => Carbon::parse($entry->period_start)->format('M d, Y'),
            'periodEnd' => Carbon::parse($entry->period_end)->format('M d, Y'),
            'referenceNumber' => $entry->payment_reference ?: 'DVX-PS-'.str_pad((string) $entry->id, 8, '0', STR_PAD_LEFT),
            'earnings' => $earnings,
            'deductions' => $deductions,
            'grossCents' => $entry->gross_cents,
            'deductionCents' => $entry->deduction_cents,
            'netCents' => $entry->net_cents,
        ])->render();
    }

    public function filename(AccountingEntry $entry): string
    {
        $employeeId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($entry->employee->username ?: $entry->user_id));

        return 'Divertex-Payslip-'.$employeeId.'-'.Carbon::parse($entry->period_start)->format('Ymd').'-'.Carbon::parse($entry->period_end)->format('Ymd').'.pdf';
    }

    /** @return array<int, array{label: string, rate: string|null, hours: string|null, amount_cents: int}> */
    private function earnings(mixed $rows, int $grossCents): array
    {
        if (! is_array($rows)) {
            return [['label' => 'Gross earnings', 'rate' => null, 'hours' => null, 'amount_cents' => $grossCents]];
        }

        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['label'], $row['amount_cents'])) {
                continue;
            }
            $result[] = [
                'label' => (string) $row['label'],
                'rate' => isset($row['rate']) ? (string) $row['rate'] : null,
                'hours' => isset($row['hours']) ? (string) $row['hours'] : null,
                'amount_cents' => (int) $row['amount_cents'],
            ];
        }

        return $result ?: [['label' => 'Gross earnings', 'rate' => null, 'hours' => null, 'amount_cents' => $grossCents]];
    }

    /** @return array<int, array{label: string, amount_cents: int}> */
    private function deductions(mixed $rows, int $deductionCents): array
    {
        if (! is_array($rows)) {
            return [['label' => 'Total deductions', 'amount_cents' => $deductionCents]];
        }

        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['label'], $row['amount_cents'])) {
                continue;
            }
            $result[] = ['label' => (string) $row['label'], 'amount_cents' => (int) $row['amount_cents']];
        }

        return $result ?: [['label' => 'Total deductions', 'amount_cents' => $deductionCents]];
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'Administrator',
            'accounting' => 'Accounting',
            'team_leader' => 'Team Leader',
            'qa_admin' => 'QA Assessment Admin',
            'manager' => 'Manager',
            default => 'Agent',
        };
    }
}
