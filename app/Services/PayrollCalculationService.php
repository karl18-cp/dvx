<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class PayrollCalculationService
{
    public function calculate(array $data, User $employee): array
    {
        $attendance = app(PayrollAttendanceService::class)->summary($employee, $data);
        $monthEnd = $data['payout'] === 'month_end';
        $money = app(AccountingService::class);
        $basic = $money->money($data['basic_rate']);
        $regular = $money->money($data['regular_rate']);
        // Rates are represented in hundredths of a cent; round only each earnings line.
        $rates = ['basic_hours' => $basic * 100, 'allowance_hours' => $regular * 100 - $basic * 110,
            'night_hours' => $basic * 10, 'overtime_hours' => $basic * 125,
            'rest_hours' => $basic * 30, 'holiday_hours' => $basic * 100];
        if ($rates['allowance_hours'] < 0) {
            throw ValidationException::withMessages(['regular_rate' => 'Regular rate must cover basic pay plus its 10% night differential, as in the workbook.']);
        }
        $labels = ['basic_hours' => 'Basic pay', 'allowance_hours' => 'Variable allowance', 'night_hours' => 'Night differential',
            'overtime_hours' => 'Overtime', 'rest_hours' => 'RD / SNWH premium', 'holiday_hours' => 'Regular holiday'];
        $lines = [];
        foreach ($rates as $key => $rate) {
            $minutes = $attendance['minutes'][$key];
            $lines[] = ['label' => $labels[$key], 'rate' => number_format($rate / 10000, 4, '.', ''), 'hours' => $attendance['hours'][$key], 'minutes' => $minutes,
                'amount_cents' => intdiv($rate * $minutes + 3000, 6000)];
        }
        $lines[] = ['label' => 'Fixed allowances', 'rate' => null, 'hours' => null, 'amount_cents' => $money->money($data['fixed_allowances'])];
        $deductions = [];
        foreach (['sss' => 'SSS', 'philhealth' => 'PhilHealth', 'pagibig' => 'Pag-IBIG', 'other_deductions' => 'Other deductions'] as $key => $label) {
            $amount = $money->money($data[$key]);
            if (! $monthEnd && $amount > 0) {
                throw ValidationException::withMessages([$key => 'Deductions apply only to the month-end payout.']);
            }
            $deductions[] = ['label' => $label, 'amount_cents' => $amount];
        }
        if ($money->money($data['other_deductions']) > 0 && empty(trim($data['notes'] ?? ''))) {
            throw ValidationException::withMessages(['notes' => 'Explain other deductions in the payroll notes.']);
        }

        return ['period_start' => $attendance['period_start'], 'period_end' => $attendance['period_end'], 'pay_date' => $attendance['pay_date'],
            'pay_month' => $data['pay_month'], 'payout' => $data['payout'], 'version' => 2, 'attendance' => $attendance,
            'inputs' => array_intersect_key($data, array_flip(['pay_month', 'payout', 'regular_rate', 'basic_rate', 'fixed_allowances', 'sss', 'philhealth', 'pagibig', 'other_deductions', 'notes', 'regular_holidays', 'special_holidays'])),
            'regular_rate' => $data['regular_rate'], 'basic_rate' => $data['basic_rate'],
            'earnings' => $lines, 'deductions' => $deductions,
            'gross_cents' => array_sum(array_column($lines, 'amount_cents')),
            'deduction_cents' => array_sum(array_column($deductions, 'amount_cents'))];
    }
}
