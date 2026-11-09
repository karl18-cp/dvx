import { useForm } from '@inertiajs/react';
import {
    Alert,
    Button,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    MenuItem,
    TextField,
} from '@mui/material';
import { Download, FileSpreadsheet, Pencil, RefreshCw, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { money } from '@/components/training-allowance-details';

type ReportType =
    | 'sss'
    | 'philhealth'
    | 'pagibig'
    | 'withholding_tax'
    | 'bir_2316'
    | 'annual_payroll';

type Row = {
    user_id: number;
    employee_id: string;
    employee_name: string;
    email: string;
    tin: string | null;
    rdo_code: string | null;
    sss_number: string | null;
    philhealth_number: string | null;
    pagibig_number: string | null;
    payroll_count: number;
    gross_cents: number;
    sss_cents: number;
    philhealth_cents: number;
    pagibig_cents: number;
    statutory_cents: number;
    withholding_tax_cents: number;
    other_deductions_cents: number;
    taxable_compensation_cents: number;
    adjustments_cents: number;
    net_pay_cents: number;
    missing_identifiers: string[];
};

type Report = {
    year: number;
    month: number | null;
    rows: Row[];
    totals: {
        employees: number;
        gross_cents: number;
        sss_cents: number;
        philhealth_cents: number;
        pagibig_cents: number;
        withholding_tax_cents: number;
        adjustments_cents: number;
        net_pay_cents: number;
        missing_identifier_records: number;
    };
};

const reports: { value: ReportType; label: string; description: string }[] = [
    { value: 'sss', label: 'SSS', description: 'SSS contribution register' },
    {
        value: 'philhealth',
        label: 'PhilHealth',
        description: 'PhilHealth contribution register',
    },
    {
        value: 'pagibig',
        label: 'Pag-IBIG',
        description: 'Pag-IBIG contribution register',
    },
    {
        value: 'withholding_tax',
        label: 'BIR withholding tax',
        description: 'Compensation and tax withheld',
    },
    {
        value: 'bir_2316',
        label: 'BIR Form 2316 worksheet',
        description: 'Annual employee tax certificate data',
    },
    {
        value: 'annual_payroll',
        label: 'Annual payroll summary',
        description: 'Earnings, deductions, adjustments, and net pay',
    },
];

const currentYear = Number(
    new Intl.DateTimeFormat('en', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
    }).format(new Date()),
);

export default function GovernmentTaxReports() {
    const [year, setYear] = useState(currentYear);
    const [month, setMonth] = useState('');
    const [type, setType] = useState<ReportType>('annual_payroll');
    const [report, setReport] = useState<Report | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [editing, setEditing] = useState<Row | null>(null);
    const details = useForm({
        tin: '',
        rdo_code: '',
        sss_number: '',
        philhealth_number: '',
        pagibig_number: '',
    });

    const load = useCallback(async () => {
        setLoading(true);
        setError('');
        const query = new URLSearchParams({ year: String(year) });
        if (month) query.set('month', month);
        try {
            const response = await fetch(
                `/accounting/government-reports?${query}`,
                { headers: { Accept: 'application/json' } },
            );
            if (!response.ok)
                throw new Error('Unable to load payroll reports.');
            setReport(await response.json());
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : 'Unable to load payroll reports.',
            );
        } finally {
            setLoading(false);
        }
    }, [month, year]);

    useEffect(() => {
        void load();
    }, [load]);

    const openDetails = (row: Row) => {
        setEditing(row);
        details.setData({
            tin: row.tin || '',
            rdo_code: row.rdo_code || '',
            sss_number: row.sss_number || '',
            philhealth_number: row.philhealth_number || '',
            pagibig_number: row.pagibig_number || '',
        });
        details.clearErrors();
    };

    const exportUrl = () => {
        const query = new URLSearchParams({ year: String(year), type });
        if (month) query.set('month', month);
        return `/accounting/government-reports/export?${query}`;
    };

    const visibleAmount = (row: Row) => {
        if (type === 'sss') return row.sss_cents;
        if (type === 'philhealth') return row.philhealth_cents;
        if (type === 'pagibig') return row.pagibig_cents;
        if (type === 'withholding_tax' || type === 'bir_2316')
            return row.withholding_tax_cents;
        return row.net_pay_cents;
    };

    return (
        <div className="grid gap-5">
            <div className="flex flex-col gap-4 rounded-2xl border border-red-100 bg-gradient-to-r from-red-50 to-white p-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div className="flex items-center gap-3">
                        <div className="rounded-xl bg-red-100 p-3 text-red-700">
                            <FileSpreadsheet size={24} />
                        </div>
                        <div>
                            <h2 className="text-xl font-bold">
                                Government and tax reports
                            </h2>
                            <p className="text-sm text-slate-600">
                                Generated from paid payroll records only.
                            </p>
                        </div>
                    </div>
                </div>
                <div className="grid gap-3 sm:grid-cols-[120px_160px_auto]">
                    <TextField
                        size="small"
                        type="number"
                        label="Year"
                        value={year}
                        onChange={(event) =>
                            setYear(Number(event.target.value))
                        }
                    />
                    <TextField
                        size="small"
                        select
                        label="Reporting month"
                        value={month}
                        onChange={(event) => setMonth(event.target.value)}
                    >
                        <MenuItem value="">Full year</MenuItem>
                        {Array.from({ length: 12 }, (_, index) => (
                            <MenuItem key={index + 1} value={index + 1}>
                                {new Intl.DateTimeFormat('en', {
                                    month: 'long',
                                }).format(new Date(2026, index, 1))}
                            </MenuItem>
                        ))}
                    </TextField>
                    <Button
                        variant="outlined"
                        startIcon={<RefreshCw size={17} />}
                        onClick={() => void load()}
                        disabled={loading}
                    >
                        Refresh
                    </Button>
                </div>
            </div>

            {error && <Alert severity="error">{error}</Alert>}
            {report?.totals.missing_identifier_records ? (
                <Alert severity="warning">
                    {report.totals.missing_identifier_records} employee
                    record(s) are missing one or more government numbers. Update
                    them before filing.
                </Alert>
            ) : null}

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {[
                    ['Gross payroll', report?.totals.gross_cents || 0],
                    [
                        'Government contributions',
                        (report?.totals.sss_cents || 0) +
                            (report?.totals.philhealth_cents || 0) +
                            (report?.totals.pagibig_cents || 0),
                    ],
                    ['Tax withheld', report?.totals.withholding_tax_cents || 0],
                    ['Net payroll', report?.totals.net_pay_cents || 0],
                ].map(([label, amount]) => (
                    <div
                        key={String(label)}
                        className="rounded-2xl border border-slate-200 bg-white p-4"
                    >
                        <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                            {label}
                        </p>
                        <p className="mt-2 text-xl font-bold">
                            {money(Number(amount))}
                        </p>
                    </div>
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-[290px_1fr]">
                <div className="grid content-start gap-2 rounded-2xl border border-slate-200 bg-white p-3">
                    {reports.map((item) => (
                        <button
                            type="button"
                            key={item.value}
                            onClick={() => setType(item.value)}
                            className={`rounded-xl p-3 text-left transition ${
                                type === item.value
                                    ? 'bg-red-50 text-red-800 ring-1 ring-red-200'
                                    : 'hover:bg-slate-50'
                            }`}
                        >
                            <span className="block font-bold">
                                {item.label}
                            </span>
                            <span className="block text-xs text-slate-500">
                                {item.description}
                            </span>
                        </button>
                    ))}
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4">
                        <div>
                            <h3 className="font-bold">
                                {
                                    reports.find((item) => item.value === type)
                                        ?.label
                                }
                            </h3>
                            <p className="text-xs text-slate-500">
                                {report?.totals.employees || 0} employee(s) ·
                                PHP amounts
                            </p>
                        </div>
                        <Button
                            component="a"
                            href={exportUrl()}
                            variant="contained"
                            startIcon={<Download size={17} />}
                            disabled={!report?.rows.length}
                        >
                            Download CSV
                        </Button>
                    </div>
                    <div className="max-h-[60vh] overflow-auto">
                        <table className="w-full min-w-[820px] text-left text-sm">
                            <thead className="sticky top-0 bg-slate-50 text-slate-700">
                                <tr>
                                    <th className="p-3">Employee</th>
                                    <th className="p-3">Government ID</th>
                                    <th className="p-3">Gross</th>
                                    <th className="p-3">Report amount</th>
                                    <th className="p-3">Readiness</th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {report?.rows.map((row) => {
                                    const identifier =
                                        type === 'sss'
                                            ? row.sss_number
                                            : type === 'philhealth'
                                              ? row.philhealth_number
                                              : type === 'pagibig'
                                                ? row.pagibig_number
                                                : row.tin;
                                    return (
                                        <tr
                                            key={row.user_id}
                                            className="border-t border-slate-100"
                                        >
                                            <td className="p-3">
                                                <p className="font-bold">
                                                    {row.employee_name}
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    {row.employee_id}
                                                </p>
                                            </td>
                                            <td className="p-3 font-mono text-xs">
                                                {identifier || 'Not provided'}
                                            </td>
                                            <td className="p-3">
                                                {money(row.gross_cents)}
                                            </td>
                                            <td className="p-3 font-bold">
                                                {money(visibleAmount(row))}
                                            </td>
                                            <td className="p-3">
                                                <Chip
                                                    size="small"
                                                    color={
                                                        row.missing_identifiers
                                                            .length
                                                            ? 'warning'
                                                            : 'success'
                                                    }
                                                    label={
                                                        row.missing_identifiers
                                                            .length
                                                            ? `Missing ${row.missing_identifiers.join(', ')}`
                                                            : 'Ready'
                                                    }
                                                />
                                            </td>
                                            <td className="p-3">
                                                <IconButton
                                                    aria-label="Edit government identifiers"
                                                    onClick={() =>
                                                        openDetails(row)
                                                    }
                                                >
                                                    <Pencil size={17} />
                                                </IconButton>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                        {!loading && !report?.rows.length && (
                            <p className="p-10 text-center text-slate-500">
                                No paid payroll records exist for this period.
                            </p>
                        )}
                    </div>
                </div>
            </div>

            <Alert severity="info">
                The BIR 2316 export is an accounting worksheet built from paid
                payroll. Confirm taxable and non-taxable classifications before
                transferring the figures to the official BIR form or e-filing
                system.
            </Alert>

            <Dialog
                open={Boolean(editing)}
                onClose={() => !details.processing && setEditing(null)}
                fullWidth
                maxWidth="sm"
            >
                <DialogTitle className="flex items-center justify-between">
                    Government identifiers
                    <IconButton onClick={() => setEditing(null)}>
                        <X />
                    </IconButton>
                </DialogTitle>
                <DialogContent dividers className="grid gap-4 pt-5!">
                    <Alert severity="info">
                        {editing?.employee_name} · {editing?.employee_id}
                    </Alert>
                    {Object.values(details.errors).map((message, index) => (
                        <Alert key={index} severity="error">
                            {message}
                        </Alert>
                    ))}
                    <TextField
                        label="TIN"
                        value={details.data.tin}
                        onChange={(event) =>
                            details.setData('tin', event.target.value)
                        }
                    />
                    <TextField
                        label="RDO code"
                        value={details.data.rdo_code}
                        onChange={(event) =>
                            details.setData('rdo_code', event.target.value)
                        }
                    />
                    <TextField
                        label="SSS number"
                        value={details.data.sss_number}
                        onChange={(event) =>
                            details.setData('sss_number', event.target.value)
                        }
                    />
                    <TextField
                        label="PhilHealth number"
                        value={details.data.philhealth_number}
                        onChange={(event) =>
                            details.setData(
                                'philhealth_number',
                                event.target.value,
                            )
                        }
                    />
                    <TextField
                        label="Pag-IBIG MID number"
                        value={details.data.pagibig_number}
                        onChange={(event) =>
                            details.setData(
                                'pagibig_number',
                                event.target.value,
                            )
                        }
                    />
                </DialogContent>
                <DialogActions sx={{ p: 3 }}>
                    <Button onClick={() => setEditing(null)}>Cancel</Button>
                    <Button
                        variant="contained"
                        disabled={details.processing || !editing}
                        onClick={() =>
                            editing &&
                            details.put(
                                `/accounting/government-details/${editing.user_id}`,
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        setEditing(null);
                                        void load();
                                    },
                                },
                            )
                        }
                    >
                        Save identifiers
                    </Button>
                </DialogActions>
            </Dialog>
        </div>
    );
}
