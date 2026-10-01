import { Alert, Button, Chip, MenuItem, TextField } from '@mui/material';
import { Calculator, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { money } from '@/components/training-allowance-details';

type Row = {
    employee: {
        id: number;
        name: string;
        username: string;
        role: string;
        status: string;
    };
    eligible_basic_cents: number;
    eligible_minutes: number;
    thirteenth_month_cents: number;
    payroll_count: number;
    last_payroll_date: string | null;
    missing_payroll_count: number;
    data_complete: boolean;
};
type Result = {
    records: {
        data: Row[];
        current_page: number;
        last_page: number;
        total: number;
    };
    summary: {
        eligible_basic_cents: number;
        thirteenth_month_cents: number;
        employees_with_accrual: number;
        incomplete_records: number;
    };
    year: number;
};

const currentYear = new Intl.DateTimeFormat('en', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
}).format(new Date());

export default function ThirteenthMonthPay() {
    const [filters, setFilters] = useState({
        year: currentYear,
        search: '',
        status: '',
        page: '1',
    });
    const [applied, setApplied] = useState(filters);
    const [result, setResult] = useState<Result | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        fetch(`/accounting/thirteenth-month?${new URLSearchParams(applied)}`, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal: controller.signal,
        })
            .then(async (response) => {
                const data = await response.json();
                if (!response.ok)
                    throw new Error(
                        data.message || 'Unable to load 13th-month accruals.',
                    );
                return data as Result;
            })
            .then((data) => {
                if (!controller.signal.aborted) {
                    setResult(data);
                    setError('');
                }
            })
            .catch((reason: Error) => {
                if (!controller.signal.aborted) setError(reason.message);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoading(false);
            });
        return () => controller.abort();
    }, [applied]);

    return (
        <div className="space-y-5">
            <div>
                <h2 className="flex items-center gap-2 text-xl font-bold">
                    <Calculator className="text-red-700" size={22} />
                    Running 13th-month pay
                </h2>
                <p className="text-sm text-slate-500">
                    Computed from each paid payroll’s Basic pay line, which
                    already follows recorded attendance.
                </p>
            </div>
            <Alert severity="info">
                Formula: total paid basic salary earned during the selected year
                ÷ 12. Allowances, premiums, overtime, and unpaid payroll drafts
                are excluded.
            </Alert>
            {error && (
                <Alert severity="error" onClose={() => setError('')}>
                    {error}
                </Alert>
            )}
            <div className="grid gap-3 sm:grid-cols-3">
                {[
                    [
                        'Paid basic earnings',
                        money(result?.summary.eligible_basic_cents ?? 0),
                    ],
                    [
                        'Running 13th-month accrual',
                        money(result?.summary.thirteenth_month_cents ?? 0),
                    ],
                    [
                        'Employees with accrual',
                        result?.summary.employees_with_accrual ?? 0,
                    ],
                ].map(([label, value]) => (
                    <div
                        key={label}
                        className="rounded-xl border border-slate-200 p-4"
                    >
                        <p className="text-xs text-slate-500">{label}</p>
                        <p className="mt-1 text-2xl font-bold">{value}</p>
                    </div>
                ))}
            </div>
            <form
                className="grid gap-3 md:grid-cols-[150px_1fr_190px_auto]"
                onSubmit={(event) => {
                    event.preventDefault();
                    setApplied({ ...filters, page: '1' });
                }}
            >
                <TextField
                    size="small"
                    type="number"
                    label="Year"
                    value={filters.year}
                    onChange={(event) =>
                        setFilters({ ...filters, year: event.target.value })
                    }
                    inputProps={{ min: 2020, max: Number(currentYear) }}
                />
                <TextField
                    size="small"
                    label="Search employee or ID"
                    value={filters.search}
                    onChange={(event) =>
                        setFilters({ ...filters, search: event.target.value })
                    }
                />
                <TextField
                    size="small"
                    select
                    label="Account status"
                    value={filters.status}
                    onChange={(event) =>
                        setFilters({ ...filters, status: event.target.value })
                    }
                >
                    <MenuItem value="">All statuses</MenuItem>
                    {[
                        'active',
                        'floating',
                        'resigned',
                        'suspended',
                        'terminated',
                    ].map((status) => (
                        <MenuItem key={status} value={status}>
                            {status.replaceAll('_', ' ')}
                        </MenuItem>
                    ))}
                </TextField>
                <Button
                    type="submit"
                    variant="outlined"
                    startIcon={<Search size={17} />}
                >
                    Filter
                </Button>
            </form>
            <div className="overflow-auto rounded-xl border border-slate-200">
                <table className="w-full min-w-[900px] text-left text-sm">
                    <thead className="bg-red-50 text-red-800">
                        <tr>
                            <th className="p-3">Employee</th>
                            <th className="p-3">Status</th>
                            <th className="p-3">Attendance hours</th>
                            <th className="p-3">Paid basic earnings</th>
                            <th className="p-3">13th-month accrual</th>
                            <th className="p-3">Paid payrolls</th>
                            <th className="p-3">Latest period</th>
                        </tr>
                    </thead>
                    <tbody>
                        {result?.records.data.map((row) => (
                            <tr
                                key={row.employee.id}
                                className="border-t border-slate-100"
                            >
                                <td className="p-3">
                                    <p className="font-bold">
                                        {row.employee.name}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {row.employee.username}
                                    </p>
                                </td>
                                <td className="p-3">
                                    <Chip
                                        size="small"
                                        label={row.employee.status.replaceAll(
                                            '_',
                                            ' ',
                                        )}
                                    />
                                </td>
                                <td className="p-3">
                                    {(row.eligible_minutes / 60).toFixed(2)}
                                </td>
                                <td className="p-3">
                                    {money(row.eligible_basic_cents)}
                                </td>
                                <td className="p-3 font-bold text-red-700">
                                    {money(row.thirteenth_month_cents)}
                                </td>
                                <td className="p-3">
                                    {row.payroll_count}
                                    {!row.data_complete && (
                                        <p className="text-xs text-amber-700">
                                            {row.missing_payroll_count} older
                                            record(s) need review
                                        </p>
                                    )}
                                </td>
                                <td className="p-3">
                                    {row.last_payroll_date || '—'}
                                </td>
                            </tr>
                        ))}
                        {!loading && !result?.records.data.length && (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="p-10 text-center text-slate-500"
                                >
                                    No employees match these filters.
                                </td>
                            </tr>
                        )}
                        {loading && (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="p-10 text-center text-slate-500"
                                >
                                    Calculating from paid payroll…
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            {(result?.records.last_page ?? 1) > 1 && (
                <div className="flex justify-end gap-2">
                    <Button
                        disabled={(result?.records.current_page ?? 1) <= 1}
                        onClick={() =>
                            setApplied({
                                ...applied,
                                page: String(
                                    (result?.records.current_page ?? 1) - 1,
                                ),
                            })
                        }
                    >
                        Previous
                    </Button>
                    <Button
                        disabled={
                            (result?.records.current_page ?? 1) >=
                            (result?.records.last_page ?? 1)
                        }
                        onClick={() =>
                            setApplied({
                                ...applied,
                                page: String(
                                    (result?.records.current_page ?? 1) + 1,
                                ),
                            })
                        }
                    >
                        Next
                    </Button>
                </div>
            )}
        </div>
    );
}
