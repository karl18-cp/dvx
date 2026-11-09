<?php

namespace App\Http\Controllers;

use App\Models\EmployeeGovernmentDetail;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AuditLogService;
use App\Services\GovernmentTaxReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GovernmentTaxReportController extends Controller
{
    public function index(Request $request, AccountingService $accounting, GovernmentTaxReportService $reports)
    {
        $accounting->authorize($request->user());
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:'.(now('Asia/Manila')->year + 1)],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        return response()->json($reports->generate((int) $data['year'], isset($data['month']) ? (int) $data['month'] : null))
            ->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, AccountingService $accounting, GovernmentTaxReportService $reports)
    {
        abort_unless(app(\App\Services\RolePermissionService::class)->can($request->user(), 'reports.export'), 403);
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:'.(now('Asia/Manila')->year + 1)],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'type' => ['required', Rule::in(GovernmentTaxReportService::TYPES)],
        ]);
        $report = $reports->generate((int) $data['year'], isset($data['month']) ? (int) $data['month'] : null);
        $rows = $reports->csvRows($data['type'], $report);
        $period = $data['year'].(isset($data['month']) ? '-'.str_pad((string) $data['month'], 2, '0', STR_PAD_LEFT) : '-annual');
        $filename = 'Divertex-'.$data['type'].'-'.$period.'.csv';

        app(AuditLogService::class)->record($request->user(), 'payroll', 'Government report exported', 'government_tax_report', metadata: ['report_type' => $data['type'], 'year' => (int) $data['year'], 'month' => $data['month'] ?? null, 'employee_count' => count($report['rows'])]);

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function details(Request $request, User $employee, AccountingService $accounting)
    {
        $accounting->authorizePayroll($request->user());
        abort_if($employee->role === 'trainee', 422, 'Government payroll identifiers are maintained for employees.');
        $data = $request->validate([
            'tin' => ['nullable', 'string', 'max:30', 'regex:/^[0-9\- ]*$/'],
            'rdo_code' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\- ]*$/'],
            'sss_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9\- ]*$/'],
            'philhealth_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9\- ]*$/'],
            'pagibig_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9\- ]*$/'],
        ]);
        $before = $employee->governmentDetails?->only(array_keys($data));
        $detail = EmployeeGovernmentDetail::updateOrCreate(
            ['user_id' => $employee->id],
            [...array_map(fn ($value) => filled($value) ? trim($value) : null, $data), 'updated_by' => $request->user()->id],
        );
        $changed = collect(array_keys($data))->filter(fn ($field) => ($before[$field] ?? null) !== $detail->{$field})->values()->all();
        app(AuditLogService::class)->record($request->user(), 'payroll', 'Employee government identifiers updated', $detail, metadata: ['changed_fields' => $changed], subjectId: $employee->id);

        return back()->with('status', 'Government and tax identifiers updated.');
    }
}
