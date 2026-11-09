<?php

namespace App\Services;

use App\Models\AccountingEntry;

class GovernmentTaxReportService
{
    public const TYPES = ['sss', 'philhealth', 'pagibig', 'withholding_tax', 'bir_2316', 'annual_payroll'];

    public function generate(int $year, ?int $month = null): array
    {
        $entries = AccountingEntry::query()
            ->with(['employee:id,name,username,email', 'employee.personalInformation:user_id,address,birth_date', 'employee.governmentDetails:user_id,tin,rdo_code,sss_number,philhealth_number,pagibig_number'])
            ->where('status', 'paid')
            ->whereIn('kind', ['payroll', 'payroll_adjustment'])
            ->whereYear('paid_at', $year)
            ->when($month, fn ($query) => $query->whereMonth('paid_at', $month))
            ->orderBy('user_id')->orderBy('paid_at')->get();

        $rows = $entries->groupBy('user_id')->map(function ($employeeEntries) {
            $employee = $employeeEntries->first()->employee;
            $payrolls = $employeeEntries->where('kind', 'payroll');
            $adjustments = $employeeEntries->where('kind', 'payroll_adjustment')->sum(function (AccountingEntry $entry) {
                return $entry->correction_direction === 'debit' ? -$entry->net_cents : $entry->net_cents;
            });
            $gross = (int) $payrolls->sum('gross_cents');
            $deductions = (int) $payrolls->sum('deduction_cents');
            $sss = $this->deduction($payrolls, 'SSS');
            $philhealth = $this->deduction($payrolls, 'PhilHealth');
            $pagibig = $this->deduction($payrolls, 'Pag-IBIG');
            $withholding = $this->deduction($payrolls, 'BIR withholding tax');
            $statutory = $sss + $philhealth + $pagibig;
            $details = $employee->governmentDetails;

            return [
                'user_id' => $employee->id,
                'employee_id' => $employee->username,
                'employee_name' => $employee->name,
                'email' => $employee->email,
                'address' => $employee->personalInformation?->address,
                'birth_date' => $employee->personalInformation?->birth_date?->toDateString(),
                'tin' => $details?->tin,
                'rdo_code' => $details?->rdo_code,
                'sss_number' => $details?->sss_number,
                'philhealth_number' => $details?->philhealth_number,
                'pagibig_number' => $details?->pagibig_number,
                'payroll_count' => $payrolls->count(),
                'gross_cents' => $gross,
                'sss_cents' => $sss,
                'philhealth_cents' => $philhealth,
                'pagibig_cents' => $pagibig,
                'statutory_cents' => $statutory,
                'withholding_tax_cents' => $withholding,
                'other_deductions_cents' => max(0, $deductions - $statutory - $withholding),
                'taxable_compensation_cents' => max(0, $gross - $statutory),
                'adjustments_cents' => $adjustments,
                'net_pay_cents' => (int) $payrolls->sum('net_cents') + $adjustments,
                'missing_identifiers' => array_values(array_filter([
                    empty($details?->tin) ? 'TIN' : null,
                    empty($details?->sss_number) ? 'SSS' : null,
                    empty($details?->philhealth_number) ? 'PhilHealth' : null,
                    empty($details?->pagibig_number) ? 'Pag-IBIG' : null,
                ])),
            ];
        })->values();

        $totals = [
            'employees' => $rows->count(),
            'gross_cents' => (int) $rows->sum('gross_cents'),
            'sss_cents' => (int) $rows->sum('sss_cents'),
            'philhealth_cents' => (int) $rows->sum('philhealth_cents'),
            'pagibig_cents' => (int) $rows->sum('pagibig_cents'),
            'withholding_tax_cents' => (int) $rows->sum('withholding_tax_cents'),
            'adjustments_cents' => (int) $rows->sum('adjustments_cents'),
            'net_pay_cents' => (int) $rows->sum('net_pay_cents'),
            'missing_identifier_records' => $rows->filter(fn ($row) => count($row['missing_identifiers']) > 0)->count(),
        ];

        return ['year' => $year, 'month' => $month, 'rows' => $rows, 'totals' => $totals];
    }

    public function csvRows(string $type, array $report): array
    {
        $columns = match ($type) {
            'sss' => ['Employee ID', 'Employee name', 'SSS number', 'Gross compensation', 'SSS contribution'],
            'philhealth' => ['Employee ID', 'Employee name', 'PhilHealth number', 'Gross compensation', 'PhilHealth contribution'],
            'pagibig' => ['Employee ID', 'Employee name', 'Pag-IBIG MID number', 'Gross compensation', 'Pag-IBIG contribution'],
            'withholding_tax' => ['Employee ID', 'Employee name', 'TIN', 'RDO code', 'Gross compensation', 'Statutory contributions', 'Taxable compensation basis', 'Tax withheld'],
            'bir_2316' => ['Employee ID', 'Employee name', 'TIN', 'RDO code', 'Address', 'Birth date', 'Gross compensation', 'Non-taxable statutory contributions', 'Taxable compensation basis', 'Tax withheld'],
            default => ['Employee ID', 'Employee name', 'Payroll count', 'Gross earnings', 'SSS', 'PhilHealth', 'Pag-IBIG', 'BIR withholding tax', 'Other deductions', 'Adjustments', 'Net pay'],
        };

        $rows = [$columns];
        foreach ($report['rows'] as $row) {
            $rows[] = match ($type) {
                'sss' => [$row['employee_id'], $row['employee_name'], $row['sss_number'], $this->decimal($row['gross_cents']), $this->decimal($row['sss_cents'])],
                'philhealth' => [$row['employee_id'], $row['employee_name'], $row['philhealth_number'], $this->decimal($row['gross_cents']), $this->decimal($row['philhealth_cents'])],
                'pagibig' => [$row['employee_id'], $row['employee_name'], $row['pagibig_number'], $this->decimal($row['gross_cents']), $this->decimal($row['pagibig_cents'])],
                'withholding_tax' => [$row['employee_id'], $row['employee_name'], $row['tin'], $row['rdo_code'], $this->decimal($row['gross_cents']), $this->decimal($row['statutory_cents']), $this->decimal($row['taxable_compensation_cents']), $this->decimal($row['withholding_tax_cents'])],
                'bir_2316' => [$row['employee_id'], $row['employee_name'], $row['tin'], $row['rdo_code'], $row['address'], $row['birth_date'], $this->decimal($row['gross_cents']), $this->decimal($row['statutory_cents']), $this->decimal($row['taxable_compensation_cents']), $this->decimal($row['withholding_tax_cents'])],
                default => [$row['employee_id'], $row['employee_name'], $row['payroll_count'], $this->decimal($row['gross_cents']), $this->decimal($row['sss_cents']), $this->decimal($row['philhealth_cents']), $this->decimal($row['pagibig_cents']), $this->decimal($row['withholding_tax_cents']), $this->decimal($row['other_deductions_cents']), $this->decimal($row['adjustments_cents']), $this->decimal($row['net_pay_cents'])],
            };
        }

        return $rows;
    }

    private function deduction($entries, string $label): int
    {
        return (int) $entries->sum(fn (AccountingEntry $entry) => collect(data_get($entry->source_snapshot, 'payroll.deductions', []))
            ->where('label', $label)->sum('amount_cents'));
    }

    private function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
